<?php

namespace App\Http\Requests\OMS;

use App\Models\OMS\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderProjectModulesRequest extends FormRequest
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
            'modules' => ['required', 'array', 'min:1'],
            'modules.*.id' => [
                'required',
                'integer',
                Rule::exists('project_modules', 'id')->where('project_id', $projectId),
                'distinct',
            ],
            'modules.*.parent_id' => [
                'nullable',
                'integer',
                Rule::exists('project_modules', 'id')->where('project_id', $projectId),
            ],
            'modules.*.position' => ['required', 'integer', 'min:0'],
        ];
    }
}
