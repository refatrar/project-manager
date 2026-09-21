<?php

namespace App\Http\Requests\OMS;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectMemberStatus;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectMemberRequest extends FormRequest
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
        $member = $this->route('member');
        $isCreating = ! $member instanceof ProjectMember;

        $rules = [
            'role' => ['required', Rule::enum(ProjectMemberRole::class)],
            'status' => ['required', Rule::enum(ProjectMemberStatus::class)],
            'allocation_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'joined_on' => ['nullable', 'date'],
        ];

        if ($isCreating) {
            $rules['user_id'] = [
                'required',
                'integer',
                Rule::exists('team_members', 'user_id')->where('team_id', $project instanceof Project ? $project->team_id : null),
                Rule::unique('project_members', 'user_id')
                    ->where('project_id', $project instanceof Project ? $project->id : null)
                    ->whereNull('deleted_at'),
            ];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => 'That user is not a member of this team.',
            'user_id.unique' => 'That user is already a member of this project.',
        ];
    }
}
