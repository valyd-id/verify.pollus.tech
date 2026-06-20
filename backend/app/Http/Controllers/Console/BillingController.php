<?php

namespace App\Http\Controllers\Console;

use App\Helpers\GlobalHelper;
use App\Http\Controllers\Controller;
use App\Models\ConsoleUser;
use App\Services\BillingService;
use Illuminate\Http\Request;

/**
 * Prepaid account balance: view balance, top up (direct credit for now — Stripe
 * later), and list recent ledger entries. All scoped to the signed-in console user.
 */
class BillingController extends Controller
{
    public function __construct(private BillingService $billing)
    {
    }

    private function user(Request $request): ConsoleUser
    {
        return $request->attributes->get('console_user');
    }

    public function balance(Request $request)
    {
        return GlobalHelper::apiSuccess($this->present($this->user($request)));
    }

    public function topUp(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:100000',
        ]);

        $user = $this->user($request);
        $this->billing->credit($user, (float) $validated['amount'], 'top_up');

        return GlobalHelper::apiSuccess($this->present($user->refresh()));
    }

    public function transactions(Request $request)
    {
        $limit = min(100, max(1, (int) $request->query('limit', 20)));

        $transactions = $this->user($request)->transactions()
            ->orderByDesc('id')->limit($limit)->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => (float) $t->amount,
                'balance_after' => (float) $t->balance_after,
                'reason' => $t->reason,
                'reference' => $t->reference,
                'created_at' => $t->created_at?->toIso8601String(),
            ]);

        return GlobalHelper::apiSuccess(['transactions' => $transactions]);
    }

    private function present(ConsoleUser $user): array
    {
        return [
            'balance' => (float) $user->balance,
            'currency' => config('verify.currency', 'USD'),
        ];
    }
}
