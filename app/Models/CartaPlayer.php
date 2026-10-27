<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartaPlayer extends Model
{
    protected $guarded = [];

    protected $hidden = ['hand'];

    protected function casts(): array
    {
        return ['hand' => 'array', 'seat' => 'integer', 'finish_rank' => 'integer', 'finished_at' => 'datetime'];
    }
}
