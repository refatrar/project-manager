<?php

namespace App\Http\Requests\OMS;

use App\Enums\TimeLogActivityType;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartTimeLogRequest extends FormRequest
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
        $teamId = $team instanceof Team ? $team->id : null;
        $projectId = $this->input('project_id');

        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('team_id', $teamId)],
            'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')->where('project_id', $projectId)],
            'activity_type' => ['required', Rule::enum(TimeLogActivityType::class)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        foreach (['project_id', 'task_id'] as $nullable) {
            if ($this->input($nullable) === '' || $this->input($nullable) === 'none') {
                $this->merge([$nullable => null]);
            }
        }

        if ($this->input('description') === '') {
            $this->merge(['description' => null]);
        }
    }
}
