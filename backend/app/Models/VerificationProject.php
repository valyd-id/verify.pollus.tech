<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VerificationProject extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'app_id',
        'description',
        'logo',
        'api_key_hash',
        'api_key_prefix',
        'webhook_url',
        'webhook_signing_secret',
        'allowed_features',
        'is_active',
        'is_default',
    ];

    protected $hidden = [
        'api_key_hash',
        'webhook_signing_secret',
    ];

    protected function casts(): array
    {
        return [
            'allowed_features' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function owner()
    {
        return $this->belongsTo(ConsoleUser::class, 'user_id');
    }

    public function workflows()
    {
        return $this->hasMany(VerificationWorkflow::class, 'project_id');
    }

    public function sessions()
    {
        return $this->hasMany(VerificationSession::class, 'project_id');
    }

    public static function hashKey(string $rawKey): string
    {
        return hash('sha256', $rawKey);
    }

    /**
     * Generate a fresh, URL-safe secret API key, e.g.
     * "_Z09E5ezfER-urHLWIZJMfvoXLuOtesdUUPQnEu4tck" (43 base64url chars).
     */
    public static function generateApiKey(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Generate a new project + its raw API key. Returns [VerificationProject, string $rawKey].
     * The raw key is only available here — only its hash is stored.
     */
    public static function provision(string $name, array $attributes = []): array
    {
        $rawKey = static::generateApiKey();

        $project = static::create(array_merge([
            'name' => $name,
            'app_id' => (string) Str::uuid(),
            'api_key_hash' => static::hashKey($rawKey),
            'api_key_prefix' => substr($rawKey, 0, 6),
            'is_active' => true,
        ], $attributes));

        return [$project, $rawKey];
    }

    public function allowsFeature(string $feature): bool
    {
        if (empty($this->allowed_features)) {
            return true;
        }
        return in_array($feature, $this->allowed_features, true);
    }
}
