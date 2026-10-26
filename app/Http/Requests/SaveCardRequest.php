<?php

namespace App\Http\Requests;

use App\Domains\FootballDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        $football = $this->routeIs('football.cards.*');
        $domain = $football ? 'football' : 'custom';

        return [
            'name' => ['required', 'string', 'min:1', 'max:60', Rule::unique('cards')->where('domain', $domain)->ignore($this->route('card')?->id)],
            'power' => $football ? ['prohibited'] : ['required', 'integer', 'between:1,1000'],
            'series' => $football ? ['prohibited'] : ['sometimes', 'nullable', 'string', 'max:60'],
            'ovr' => $football ? ['required', 'integer', 'between:1,100'] : ['prohibited'],
            'position' => $football ? ['required', Rule::in(array_keys(FootballDomain::REQUIREMENTS))] : ['prohibited'],
            'is_icon' => $football ? ['required', 'boolean'] : ['prohibited'],
            'image' => [$this->route('card') ? 'sometimes' : 'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
        ];
    }
}
