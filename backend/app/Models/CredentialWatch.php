<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A credential check that must be re-verified over time (scheduled cadence or
 * near expiry). The re-check command re-runs the registry lookup and, on a status
 * change, updates the IdP license record + fires a webhook to the integrator.
 */
class CredentialWatch extends Model
{
    public const POLICY_SCHEDULED = 'scheduled';
    public const POLICY_EXPIRY = 'expiry';

    protected $fillable = [
        'project_id',
        'pollus_id',
        'session_id',
        'credential',
        'license_type',
        'license_state',
        'license_number',
        'policy',
        'interval',
        'last_status',
        'expire_at',
        'last_checked_at',
        'next_recheck_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credential' => 'array',
            'expire_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'next_recheck_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function project()
    {
        return $this->belongsTo(VerificationProject::class, 'project_id');
    }
}
