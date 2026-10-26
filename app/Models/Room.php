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
        return ['settings' => 'array', 'members' => 'array', 'playback' => 'array', 'version' => 'integer', 'host' => 'integer', 'host_editing_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    /** Status shown to the admin: lobby, playing, finished, closed, banned or expired. */
    public function adminState(): string
    {
        if ($this->status === 'banned') {
            return 'banned';
        }

        return $this->isClosed() ? ($this->status === 'closed' ? 'closed' : 'expired') : $this->status;
    }

    public function hostIsEditing(): bool
    {
        return $this->status === 'lobby' && $this->host_editing_at?->gt(now()->subMinutes(10));
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
