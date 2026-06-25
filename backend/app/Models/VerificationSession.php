<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationSession extends Model
{
    public const STATUS_NOT_STARTED = 'NOT_STARTED';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_IN_REVIEW = 'IN_REVIEW';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_DECLINED = 'DECLINED';
    public const STATUS_ABANDONED = 'ABANDONED';
    public const STATUS_EXPIRED = 'EXPIRED';

    public const TERMINAL_STATUSES = [
        self::STATUS_APPROVED,
        self::STATUS_DECLINED,
        self::STATUS_ABANDONED,
        self::STATUS_EXPIRED,
    ];

    public const MODE_HOSTED = 'hosted';
    public const MODE_STANDALONE = 'standalone';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'project_id',
        'workflow_id',
        'status',
        'mode',
        'vendor_data',
        'pollus_id',
        'reused',
        'callback_url',
        'redirect_url',
        'session_token',
        'features',
        'settings',
        'metadata',
        'decision',
        'expires_at',
        'decided_at',
    ];

    protected $hidden = [
        'session_token',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'settings' => 'array',
            'metadata' => 'array',
            'decision' => 'array',
            'reused' => 'boolean',
            'expires_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function project()
    {
        return $this->belongsTo(VerificationProject::class, 'project_id');
    }

    public function checks()
    {
        return $this->hasMany(VerificationCheck::class, 'session_id');
    }

    public function documents()
    {
        return $this->hasMany(VerificationDocument::class, 'session_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
