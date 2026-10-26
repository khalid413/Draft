<?php

namespace App\Http\Requests;

class RoomSettingsRequest extends StartGameRequest
{
    protected function prepareForValidation(): void
    {
        $capacity = min(4, max(2, (int) $this->input('capacity', 2)));
        $this->merge(['players' => array_map(fn ($i) => 'Seat '.$i, range(1, $capacity)), 'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name')]);
        parent::prepareForValidation();
    }

    public function rules(): array
    {
        return [...parent::rules(), 'version' => [$this->route('room') ? 'required' : 'sometimes', 'nullable', 'integer', 'min:0'], 'capacity' => ['required', 'integer', 'between:2,4'], 'name' => ['required', 'string', 'min:2', 'max:20']];
    }

    public function settings(): array
    {
        return $this->safe()->only(['domain', 'rounds', 'catalog', 'timer', 'capacity']);
    }
}
