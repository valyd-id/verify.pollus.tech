<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A reusable verified identity for the "verify once, reuse" product. One row per
 * (project_id, pollus_id) — verify is the system of record; the IdP only supplies
 * the pollus_id. Holds the verified profile + the selfie face embedding so a
 * returning user re-verifies with a selfie alone.
 *
 * Encryption: to enable at-rest encryption later, switch the casts below to
 * `encrypted` / `encrypted:array` (the columns are text, so no schema change is
 * needed). Plaintext for testing now.
 */
class ReusableIdentity extends Model
{
    protected $fillable = [
        'project_id',
        'pollus_id',
        'vendor_data',
        'full_name',
        'dob',
        'age_bands',
        'face_embedding',
        'licenses',
        'source_session_id',
        'verified_at',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            // Plaintext for testing. Encryption-ready: change to
            // 'full_name' => 'encrypted', 'dob' => 'encrypted',
            // 'age_bands' => 'encrypted:array', 'face_embedding' => 'encrypted:array',
            // 'licenses' => 'encrypted:array',
            'age_bands' => 'array',
            'face_embedding' => 'array',
            'licenses' => 'array',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function project()
    {
        return $this->belongsTo(VerificationProject::class, 'project_id');
    }

    /** Active = not revoked and (no expiry set or not yet past). */
    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
