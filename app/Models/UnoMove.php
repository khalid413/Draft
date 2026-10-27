<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only match history. Draw rows keep the private card id for audits; views never expose it. */
class UnoMove extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['seat' => 'integer', 'target' => 'integer', 'amount' => 'integer', 'turn_number' => 'integer'];
    }
}
