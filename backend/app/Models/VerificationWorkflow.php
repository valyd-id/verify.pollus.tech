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
        ], $this->settings ?? []);
    }
}
