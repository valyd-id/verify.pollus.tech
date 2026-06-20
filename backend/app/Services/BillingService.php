<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\BillingTransaction;
use App\Models\ConsoleUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Prepaid account balance + ledger. Every charge/refund/credit runs in a DB
 * transaction with a row lock on the user so concurrent API calls can't race the
 * balance below zero. Amounts are always stored positive; the `type` carries the
 * direction.
 */
class BillingService
{
    /** Cost for a single feature/check from config pricing (0 if unpriced). */
    public function costForFeature(string $feature): float
    {
        return (float) (config('verify.pricing')[$feature] ?? 0.0);
    }

    /** Total cost for a set of features (e.g. a workflow). */
    public function costForFeatures(array $features): float
    {
        return array_sum(array_map(fn ($f) => $this->costForFeature($f), $features));
    }

    public function balance(ConsoleUser $user): float
    {
        return (float) $user->balance;
    }

    /** Add funds (top-up). */
    public function credit(ConsoleUser $user, float $amount, string $reason, ?string $reference = null, array $meta = []): ?BillingTransaction
    {
        return $this->apply($user, BillingTransaction::TYPE_CREDIT, $amount, $reason, $reference, $meta);
    }

    /** Return funds for work we couldn't complete on our side. */
    public function refund(ConsoleUser $user, float $amount, string $reason, ?string $reference = null, array $meta = []): ?BillingTransaction
    {
        return $this->apply($user, BillingTransaction::TYPE_REFUND, $amount, $reason, $reference, $meta);
    }

    /**
     * Deduct funds for API usage.
     *
     * @throws InsufficientBalanceException when the balance can't cover it.
     */
    public function charge(ConsoleUser $user, float $amount, string $reason, ?string $reference = null, array $meta = []): ?BillingTransaction
    {
        return $this->apply($user, BillingTransaction::TYPE_DEBIT, $amount, $reason, $reference, $meta);
    }

    /**
     * Ensure the account can cover `amount`, without deducting (used as the
     * upfront guard when a hosted session is created).
     *
     * @throws InsufficientBalanceException
     */
    public function assertCanAfford(ConsoleUser $user, float $amount): void
    {
        $amount = round($amount, 4);
        $available = $this->balance($user);
        if ($amount > 0 && $available + 1e-9 < $amount) {
            throw new InsufficientBalanceException($amount, $available);
        }
    }

    /**
     * Charge for a feature, run the work, and refund automatically if it fails on
     * our side. "Failure" = the callable throws, OR returns a JsonResponse (an
     * early validation/error path that did no billable work). A normal
     * passed/failed CheckResult is real work and stays charged.
     *
     * Ownerless projects (no console user) are not billed.
     *
     * @template T
     * @param  callable():T  $run
     * @return T
     * @throws InsufficientBalanceException
     */
    public function runCharged(?ConsoleUser $user, string $feature, callable $run, ?string $reference = null)
    {
        if (!$user) {
            return $run();
        }

        $cost = $this->costForFeature($feature);
        $txn = $this->charge($user, $cost, "check:{$feature}", $reference);

        try {
            $result = $run();
        } catch (\Throwable $e) {
            if ($txn) {
                $this->refund($user, $cost, "refund:{$feature}", $reference, ['error' => $e->getMessage()]);
            }
            throw $e;
        }

        if ($txn && $result instanceof JsonResponse) {
            $this->refund($user, $cost, "refund:{$feature}", $reference, ['reason' => 'not_processed']);
        }

        return $result;
    }

    private function apply(ConsoleUser $user, string $type, float $amount, string $reason, ?string $reference, array $meta): ?BillingTransaction
    {
        $amount = round($amount, 4);
        if ($amount <= 0) {
            return null; // free feature / no-op
        }

        return DB::transaction(function () use ($user, $type, $amount, $reason, $reference, $meta) {
            $locked = ConsoleUser::whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $current = (float) $locked->balance;

            if ($type === BillingTransaction::TYPE_DEBIT) {
                if ($current + 1e-9 < $amount) {
                    throw new InsufficientBalanceException($amount, $current);
                }
                $new = $current - $amount;
            } else {
                $new = $current + $amount;
            }

            $locked->balance = $new;
            $locked->save();
            $user->balance = $new; // keep the caller's instance in sync

            return BillingTransaction::create([
                'user_id' => $locked->getKey(),
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $new,
                'reason' => $reason,
                'reference' => $reference,
                'meta' => $meta ?: null,
            ]);
        });
    }
}
