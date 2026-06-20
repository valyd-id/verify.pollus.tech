<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One append-only ledger entry against a console user's balance.
 */
class BillingTransaction extends Model
{
    public const TYPE_CREDIT = 'credit'; // top-up
    public const TYPE_DEBIT = 'debit';   // API usage charge
    public const TYPE_REFUND = 'refund'; // our-side failure → money back

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'balance_after',
        'reason',
        'reference',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'balance_after' => 'decimal:4',
            'meta' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(ConsoleUser::class, 'user_id');
    }
}
