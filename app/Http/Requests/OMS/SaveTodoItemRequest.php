<?php

namespace App\Http\Requests\OMS;

use App\Enums\Priority;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTodoItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('current_team');

        return [
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'due_at' => ['nullable', 'date'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('team_members', 'user_id')->where('team_id', $team instanceof Team ? $team->id : null),
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('notes') === '') {
            $this->merge(['notes' => null]);
        }

        if ($this->input('due_at') === '') {
            $this->merge(['due_at' => null]);
        }

        if ($this->input('estimated_minutes') === '') {
            $this->merge(['estimated_minutes' => null]);
        }

        if ($this->input('assigned_to') === '' || $this->input('assigned_to') === 'none') {
            $this->merge(['assigned_to' => null]);
        }
    }
}
