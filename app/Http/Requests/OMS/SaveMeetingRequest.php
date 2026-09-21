<?php

namespace App\Http\Requests\OMS;

use App\Enums\MeetingType;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMeetingRequest extends FormRequest
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
            'sprint_id' => ['nullable', 'integer', Rule::exists('sprints', 'id')->where('project_id', $projectId)],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(MeetingType::class)],
            'agenda' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_url' => ['nullable', 'string', 'max:2048', 'url'],
            'scheduled_start' => ['required', 'date'],
            'scheduled_end' => ['required', 'date', 'after:scheduled_start'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('project_id') === '' || $this->input('project_id') === 'none') {
            $this->merge(['project_id' => null]);
        }

        if ($this->input('sprint_id') === '' || $this->input('sprint_id') === 'none') {
            $this->merge(['sprint_id' => null]);
        }

        foreach (['agenda', 'location', 'meeting_url'] as $nullable) {
            if ($this->input($nullable) === '') {
                $this->merge([$nullable => null]);
            }
        }
    }
}
