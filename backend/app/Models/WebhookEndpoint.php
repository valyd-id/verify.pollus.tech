<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A single webhook destination for a project. Events fan out to every active
 * endpoint, each signed with its own secret.
 */
class WebhookEndpoint extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'url',
        'signing_secret',
        'events',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public static function newSecret(): string
    {
        return 'whsec_' . Str::random(40);
    }

    public function project()
    {
        return $this->belongsTo(VerificationProject::class, 'project_id');
    }

    /** Whether this endpoint should receive the given event type. */
    public function wantsEvent(string $type): bool
    {
        return empty($this->events) || in_array($type, $this->events, true);
    }
}
