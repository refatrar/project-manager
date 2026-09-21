<?php

namespace App\Http\Requests\OMS;

use App\Models\OMS\Meeting;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMeetingAgendaItemRequest extends FormRequest
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
        $meeting = $this->route('meeting');
        $projectId = $meeting instanceof Meeting ? $meeting->project_id : null;

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            // A team-wide meeting (no project) has no task list to link
            // against, so linking is only offered once a project is set.
            'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')->where('project_id', $projectId)],
            'presenter_id' => ['nullable', 'integer', Rule::exists('team_members', 'user_id')->where('team_id', $teamId)],
            'is_discussed' => ['boolean'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        foreach (['description', 'notes', 'duration_minutes'] as $nullable) {
            if ($this->input($nullable) === '') {
                $this->merge([$nullable => null]);
            }
        }

        if ($this->input('task_id') === '' || $this->input('task_id') === 'none') {
            $this->merge(['task_id' => null]);
        }

        if ($this->input('presenter_id') === '' || $this->input('presenter_id') === 'none') {
            $this->merge(['presenter_id' => null]);
        }
    }
}
