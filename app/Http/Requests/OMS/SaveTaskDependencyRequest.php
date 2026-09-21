<?php

namespace App\Http\Requests\OMS;

use App\Enums\TaskDependencyType;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTaskDependencyRequest extends FormRequest
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
        $task = $this->route('task');
        $projectId = $project instanceof Project ? $project->id : null;
        $taskId = $task instanceof Task ? $task->id : null;

        return [
            'related_task_id' => [
                'required',
                'integer',
                Rule::exists('tasks', 'id')->where('project_id', $projectId),
                Rule::notIn([$taskId]),
                Rule::unique('task_dependencies', 'related_task_id')
                    ->where('task_id', $taskId)
                    ->where('type', $this->input('type')),
            ],
            'type' => ['required', Rule::enum(TaskDependencyType::class)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'related_task_id.not_in' => 'A task cannot depend on itself.',
        ];
    }
}
