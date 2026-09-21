<?php

namespace App\Http\Requests\OMS;

use App\Enums\TaskAssignmentRole;
use App\Models\OMS\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTaskRequest extends FormRequest
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
        $project = $this->route('project');
        $projectId = $project instanceof Project ? $project->id : null;

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('project_members', 'user_id')
                    ->where('project_id', $projectId)
                    ->where('status', 'active'),
            ],
            'role' => ['required', Rule::enum(TaskAssignmentRole::class)],
            'allocated_hours' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => 'That user is not an active member of this project.',
        ];
    }
}
