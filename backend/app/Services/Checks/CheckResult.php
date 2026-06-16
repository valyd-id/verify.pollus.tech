<?php

namespace App\Services\Checks;

use App\Models\VerificationCheck;

/**
 * Normalised result returned by every check runner. Maps 1:1 onto a
 * verification_checks row.
 */
class CheckResult
{
    public function __construct(
        public string $type,
        public string $status, // passed | failed | review
        public ?float $score = null,
        public array $data = [],
        public ?string $error = null,
    ) {
    }

    public static function passed(string $type, ?float $score = null, array $data = []): self
    {
        return new self($type, VerificationCheck::STATUS_PASSED, $score, $data);
    }

    public static function failed(string $type, ?string $error = null, ?float $score = null, array $data = []): self
    {
        return new self($type, VerificationCheck::STATUS_FAILED, $score, $data, $error);
    }

    public static function review(string $type, array $data = [], ?float $score = null): self
    {
        return new self($type, VerificationCheck::STATUS_REVIEW, $score, $data);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'status' => $this->status,
            'score' => $this->score,
            'data' => $this->data,
            'error' => $this->error,
        ];
    }
}
