<?php

namespace App\Http\Requests\Setup;

use App\Models\Setup\Label;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLabelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('web') !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $label = $this->route('label');
        $teamId = $this->user('web')?->currentTeam?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:64',
                Rule::unique('labels', 'name')
                    ->where('team_id', $teamId)
                    ->ignore($label instanceof Label ? $label->id : null),
            ],
            'color' => ['nullable', 'string', 'max:9'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        foreach (['color', 'description'] as $nullable) {
            if ($this->input($nullable) === '') {
                $this->merge([$nullable => null]);
            }
        }
    }
}
