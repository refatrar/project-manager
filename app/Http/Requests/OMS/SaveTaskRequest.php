<?php

namespace App\Http\Requests\OMS;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Enums\TodoListType;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\OMS\TodoList;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTaskRequest extends FormRequest
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
        $isCreating = ! $this->route('task') instanceof Task;

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'task_type_id' => ['required', 'integer', Rule::exists('task_types', 'id')],
            'project_module_id' => ['nullable', 'integer', Rule::exists('project_modules', 'id')->where('project_id', $projectId)],
            'milestone_id' => ['nullable', 'integer', Rule::exists('milestones', 'id')->where('project_id', $projectId)],
            'sprint_id' => ['nullable', 'integer', Rule::exists('sprints', 'id')->where('project_id', $projectId)],
            'priority' => ['required', Rule::enum(Priority::class)],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'remaining_hours' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'is_billable' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'label_ids' => ['array'],
            'label_ids.*' => ['integer', Rule::exists('labels', 'id')->where('team_id', $project instanceof Project ? $project->team_id : null)],
        ];

        // Optional: when `todos` is omitted the task's checklist is left
        // untouched. An `id` must name an item on this task's own checklist.
        $task = $this->route('task');
        $rules['todos'] = ['sometimes', 'array', 'max:100'];
        $rules['todos.*.title'] = ['required', 'string', 'max:255'];
        $rules['todos.*.id'] = $isCreating
            ? ['prohibited']
            : ['nullable', 'integer', 'distinct', Rule::exists('todo_items', 'id')->whereIn(
                'todo_list_id',
                TodoList::query()
                    ->where('task_id', $task instanceof Task ? $task->id : null)
                    ->where('type', TodoListType::TaskChecklist->value)
                    ->pluck('id')
                    ->all(),
            )];

        if ($isCreating) {
            $rules['status'] = ['required', Rule::enum(TaskStatus::class)];
            // parent_id is create-only: a new task can never be its own
            // ancestor, so no cycle check is needed the way modules need one.
            $rules['parent_id'] = ['nullable', 'integer', Rule::exists('tasks', 'id')->where('project_id', $projectId)];
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

        if ($this->input('project_module_id') === '' || $this->input('project_module_id') === 'none') {
            $this->merge(['project_module_id' => null]);
        }

        if ($this->input('parent_id') === '' || $this->input('parent_id') === 'none') {
            $this->merge(['parent_id' => null]);
        }

        if ($this->input('milestone_id') === '' || $this->input('milestone_id') === 'none') {
            $this->merge(['milestone_id' => null]);
        }

        if ($this->input('sprint_id') === '' || $this->input('sprint_id') === 'none') {
            $this->merge(['sprint_id' => null]);
        }

        // Blank to-do rows left in the form are dropped rather than rejected.
        if (is_array($this->input('todos'))) {
            $this->merge(['todos' => array_values(array_filter(
                $this->input('todos'),
                fn (mixed $todo): bool => ! is_array($todo) || trim((string) ($todo['title'] ?? '')) !== '',
            ))]);
        }
    }
}
