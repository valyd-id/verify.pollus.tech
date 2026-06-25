<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationWorkflow extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'project_id',
        'name',
        'features',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function project()
    {
        return $this->belongsTo(VerificationProject::class, 'project_id');
    }

    /**
     * Default settings merged with stored ones. Controls decisioning.
     */
    public function effectiveSettings(): array
    {
        return array_merge([
            // Features that must pass for APPROVED; defaults to all features.
            'required_features' => $this->features,
            // When true, auto-decide; when false, terminal checks land in IN_REVIEW.
            'auto_approve' => true,
            'face_match_threshold' => (float) config('verify.face_match_threshold'),
            'liveness_threshold' => (int) config('verify.liveness_threshold'),
            // Age bands the client wants asserted, e.g. ["is_18_plus"].
            'age_bands' => [],
            // New-flow wizard config (see the setup wizard).
            'product' => 'verify',          // verify | sso
            'mode' => 'hosted',             // hosted | standalone
            'recheck' => null,              // per_action | scheduled | expiry (credential only)
            'recheck_interval' => 'daily',  // daily | weekly (when recheck=scheduled)
            'storage' => null,              // store | recapture (standalone reuse)
            'reuse' => false,               // "verify once, reuse" enabled
        ], $this->settings ?? []);
    }
}
