<?php

namespace App\Http\Requests\OMS;

use App\Enums\Priority;
use App\Enums\ProjectHealth;
use App\Enums\ProjectStatus;
use App\Models\OMS\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectRequest extends FormRequest
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
        $team = $this->user()?->currentTeam;

        return [
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('projects', 'code')
                    ->where('team_id', $team?->id)
                    ->ignore($project instanceof Project ? $project->id : null),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'priority' => ['required', Rule::enum(Priority::class)],
            'health' => ['required', Rule::enum(ProjectHealth::class)],
            'color' => ['nullable', 'string', 'max:9'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('code')) {
            $this->merge(['code' => strtoupper((string) $this->input('code'))]);
        }

        foreach (['description', 'color', 'client_name', 'currency'] as $nullable) {
            if ($this->input($nullable) === '') {
                $this->merge([$nullable => null]);
            }
        }
    }
}
