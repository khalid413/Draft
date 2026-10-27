<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnoPlayer extends Model
{
    protected $guarded = [];

    protected $hidden = ['hand'];

    protected function casts(): array
    {
        return ['hand' => 'array', 'seat' => 'integer', 'uno_called' => 'boolean', 'finish_rank' => 'integer', 'finished_at' => 'datetime'];
    }
}
