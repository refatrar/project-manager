<?php

namespace App\Http\Requests\OMS;

use App\Enums\AllocationStatus;
use App\Models\OMS\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveResourceAllocationRequest extends FormRequest
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
            'user_id' => ['required', 'integer', Rule::exists('project_members', 'user_id')->where('project_id', $projectId)->where('status', 'active')->whereNull('deleted_at')],
            'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')->where('project_id', $projectId)],
            'sprint_id' => ['nullable', 'integer', Rule::exists('sprints', 'id')->where('project_id', $projectId)],
            'status' => ['required', Rule::enum(AllocationStatus::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'hours_per_day' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'allocation_percentage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        foreach (['task_id', 'sprint_id', 'allocation_percentage'] as $nullable) {
            if ($this->input($nullable) === '' || $this->input($nullable) === 'none') {
                $this->merge([$nullable => null]);
            }
        }

        if ($this->input('notes') === '') {
            $this->merge(['notes' => null]);
        }
    }
}
