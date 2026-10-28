<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnoGame extends Model
{
    use HasUuids;

    protected $guarded = [];

    // Hidden information never leaves through serialization by accident.
    protected $hidden = ['draw_pile', 'discard_pile', 'drawn_card'];

    protected function casts(): array
    {
        return ['draw_pile' => 'array', 'discard_pile' => 'array',
            'version' => 'integer', 'turn_number' => 'integer', 'current_seat' => 'integer', 'direction' => 'integer',
            'pending_draw' => 'integer', 'uno_exposed' => 'integer', 'turn_ends_at' => 'integer', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(UnoPlayer::class)->orderBy('seat');
    }

    public function moves(): HasMany
    {
        return $this->hasMany(UnoMove::class)->orderBy('id');
    }
}
