<?php

namespace App\Http\Requests;

class PickRequest extends GameActionRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['playerIndex' => ['required', 'integer', 'min:0'],
            'cardId' => ['required', 'string', 'size:48']]);
    }
}
