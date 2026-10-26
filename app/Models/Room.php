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
        return ['settings' => 'array', 'members' => 'array', 'playback' => 'array', 'version' => 'integer', 'host' => 'integer', 'expires_at' => 'datetime'];
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['closed', 'banned'], true) || $this->expires_at->isPast();
    }

    /** Seats still held by a player; departed seats stay in place so game indices never shift. */
    public function activeSeats(): array
    {
        return array_keys(array_filter($this->members, fn ($member) => ! ($member['left'] ?? false)));
    }
}
