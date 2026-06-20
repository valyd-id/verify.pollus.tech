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
            'balance' => 'decimal:4',
        ];
    }

    public function projects()
    {
        return $this->hasMany(VerificationProject::class, 'user_id');
    }

    public function transactions()
    {
        return $this->hasMany(BillingTransaction::class, 'user_id');
    }
}
