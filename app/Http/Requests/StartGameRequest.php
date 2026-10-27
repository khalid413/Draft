<?php

namespace App\Http\Requests;

use App\Domains\FootballDomain;
use App\Models\Card;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StartGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['players' => array_map(fn ($name) => is_string($name) ? trim($name) : $name, (array) $this->input('players', []))]);
    }

    public function rules(): array
    {
        return ['players' => ['required', 'array', 'min:2', 'max:4', 'list'], 'players.*' => ['required', 'string', 'min:2', 'max:20', 'distinct:ignore_case'],
            'domain' => ['required', 'in:custom,football'], 'rounds' => ['required', 'integer', $this->input('domain') === 'football' ? 'in:11' : 'in:3,5,7'],
            'catalog' => ['nullable', 'string', 'max:60', 'prohibited_unless:domain,custom'],
            'timer' => ['required', 'boolean'], 'replace' => ['sometimes', 'boolean']];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $playerCount = count($this->input('players'));
            if ($this->input('domain') === 'football') {
                $missing = array_filter((new FootballDomain)->inventory($playerCount), fn ($item) => $item['missing'] > 0);
                if ($missing) {
                    $validator->errors()->add('domain', __('Add :players to complete all 4-3-3 teams.', ['players' => implode(', ', array_map(fn ($item) => $item['missing'].' '.$item['position'], $missing))]));
                }
            }
            if ($this->input('domain') === 'custom' && in_array($this->integer('rounds'), [3, 5, 7], true)) {
                $required = $this->integer('rounds') * $playerCount;
                $catalog = is_string($this->input('catalog')) ? trim($this->input('catalog')) : '';
                $count = Card::where('domain', 'custom')->when($catalog !== '', fn ($query) => $query->where('series', $catalog))->count();
                if ($count < $required) {
                    $validator->errors()->add('domain', __('Add :missing more cards to play :rounds rounds (:required cards needed).', ['missing' => $required - $count, 'rounds' => $this->integer('rounds'), 'required' => $required]));
                }
            }
            $names = $this->input('players', []);
            if (count($names) === 2 && is_string($names[0] ?? null) && is_string($names[1] ?? null)
                && mb_strtolower($names[0]) === mb_strtolower($names[1])) {
                $validator->errors()->add('players', __('Choose different names for the two players.'));
            }
        }];
    }
}
