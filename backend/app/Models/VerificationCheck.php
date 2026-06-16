<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationCheck extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PASSED = 'passed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REVIEW = 'review';

    public const TYPE_ID = 'id_verification';
    public const TYPE_LIVENESS = 'liveness';
    public const TYPE_FACE_MATCH = 'face_match';
    public const TYPE_AGE = 'age';
    public const TYPE_CREDENTIAL = 'credential';

    protected $fillable = [
        'session_id',
        'type',
        'status',
        'score',
        'data',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'score' => 'float',
        ];
    }

    public function session()
    {
        return $this->belongsTo(VerificationSession::class, 'session_id');
    }
}
