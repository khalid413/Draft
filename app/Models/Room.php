<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['members'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'members' => 'array', 'playback' => 'array', 'version' => 'integer', 'expires_at' => 'datetime'];
    }
}
