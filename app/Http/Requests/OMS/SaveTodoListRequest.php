<?php

namespace App\Http\Requests\OMS;

use App\Enums\TodoListStatus;
use App\Enums\TodoListType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTodoListRequest extends FormRequest
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
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(TodoListStatus::class)],
            'scheduled_for' => ['nullable', 'date'],
        ];

        // `type` only matters on creation: task checklists, meeting action
        // lists and generated lists come from their own actions, not this
        // general-purpose form, and a list's type is not changed afterward.
        if (! $this->routeIsUpdate()) {
            $rules['type'] = ['required', Rule::in([TodoListType::Custom->value, TodoListType::Daily->value])];
            $rules['scheduled_for'][] = Rule::requiredIf($this->input('type') === TodoListType::Daily->value);
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('description') === '') {
            $this->merge(['description' => null]);
        }

        if ($this->input('scheduled_for') === '') {
            $this->merge(['scheduled_for' => null]);
        }
    }

    /**
     * Determine whether this request is updating an existing list.
     */
    private function routeIsUpdate(): bool
    {
        return $this->route('todo_list') !== null;
    }
}
