<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsoleUser extends Model
{
    protected $table = 'console_users';

    protected $fillable = [
        'valyd_user_id',
        'email',
        'name',
        'profile',
    ];

    protected function casts(): array
    {
        return [
            'profile' => 'array',
        ];
    }

    public function projects()
    {
        return $this->hasMany(VerificationProject::class, 'user_id');
    }
}
