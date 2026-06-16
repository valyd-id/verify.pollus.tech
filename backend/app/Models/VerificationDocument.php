<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationDocument extends Model
{
    public const TYPE_ID_FRONT = 'id_front';
    public const TYPE_ID_BACK = 'id_back';
    public const TYPE_SELFIE = 'selfie';

    protected $fillable = [
        'session_id',
        'type',
        'storage_path',
        'mime',
    ];

    public function session()
    {
        return $this->belongsTo(VerificationSession::class, 'session_id');
    }
}
