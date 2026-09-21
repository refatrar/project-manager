<?php

namespace App\Http\Requests\OMS;

use App\Enums\TodoListType;
use App\Models\OMS\TodoItem;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoteTodoItemRequest extends FormRequest
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
        $item = $this->route('item');

        $rules = [
            'task_type_id' => ['required', 'integer', Rule::exists('task_types', 'id')],
        ];

        // A checklist item's project is already implied by the task it
        // belongs to — asking for one would let it be promoted into an
        // unrelated project's tree. Every other item type has no project
        // of its own, so the user must choose one.
        if (! ($item instanceof TodoItem && $item->list->type === TodoListType::TaskChecklist)) {
            $rules['project_id'] = [
                'required',
                'integer',
                Rule::exists('projects', 'id')->where('team_id', $team instanceof Team ? $team->id : null),
            ];
        }

        return $rules;
    }
}
