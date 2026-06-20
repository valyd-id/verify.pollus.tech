<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a charge would take the account balance below zero. Rendered as a
 * 402 `insufficient_balance` API error (see bootstrap/app.php).
 */
class InsufficientBalanceException extends RuntimeException
{
    public function __construct(
        public readonly float $required,
        public readonly float $available,
    ) {
        parent::__construct('Insufficient balance. Top up your account to continue.');
    }
}
