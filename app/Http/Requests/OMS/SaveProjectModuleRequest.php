<?php

namespace App\Http\Requests\OMS;

use App\Enums\Priority;
use App\Enums\ProjectModuleStatus;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectModule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectModuleRequest extends FormRequest
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
        $module = $this->route('module');
        $projectId = $project instanceof Project ? $project->id : null;

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('project_modules', 'id')->where('project_id', $projectId),
                function (string $attribute, mixed $value, Closure $fail) use ($module): void {
                    if ($value !== null && $module instanceof ProjectModule && (int) $value === $module->id) {
                        $fail('A module cannot be its own parent.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(ProjectModuleStatus::class)],
            'priority' => ['required', Rule::enum(Priority::class)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
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

        if ($this->input('parent_id') === '' || $this->input('parent_id') === 'none') {
            $this->merge(['parent_id' => null]);
        }
    }
}
