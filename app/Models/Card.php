<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array', 'power' => 'integer', 'is_icon' => 'boolean'];
    }

    public function snapshot(): array
    {
        $scale = $this->domain === 'football' ? 1 : 10;

        return [
            'id' => $this->id, 'name' => $this->name, 'series' => $this->series,
            'image_path' => $this->image_path, 'power' => $this->power,
            'rarity' => $this->power > 85 * $scale ? 'legendary' : ($this->power > 65 * $scale ? 'epic' : 'rare'),
            'position' => $this->position, 'is_icon' => (bool) $this->is_icon,
        ];
    }
}
