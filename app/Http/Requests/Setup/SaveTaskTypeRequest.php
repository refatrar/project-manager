<?php

namespace App\Http\Requests\Setup;

use App\Enums\TaskTypeStatus;
use App\Models\Setup\TaskType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTaskTypeRequest extends FormRequest
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
        $taskType = $this->route('task_type');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('task_types', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($taskType instanceof TaskType ? $taskType->id : null),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(TaskTypeStatus::class)],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('description') === '') {
            $this->merge(['description' => null]);
        }
    }
}
