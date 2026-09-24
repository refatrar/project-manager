<?php

namespace App\Http\Requests\Teams;

use App\Models\Team;
use App\Services\Teams\TeamAccessControl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('team');

        abort_if(! $team instanceof Team, 404);

        return [
            'role' => ['required', 'string', Rule::in(app(TeamAccessControl::class)->grantableSlugs($this->user('web'), $team))],
        ];
    }
}
