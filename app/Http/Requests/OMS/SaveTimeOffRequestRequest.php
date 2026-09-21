<?php

namespace App\Http\Requests\OMS;

use App\Enums\TimeOffType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTimeOffRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request. `required_if`'s
     * comparison value must be the literal string `false`, not `0` — see
     * MEMORY.md's boolean-wildcard entry for why `0`/`1` silently fail to
     * match a `boolean`-validated sibling.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TimeOffType::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'is_full_day' => ['required', 'boolean'],
            'start_time' => ['nullable', 'date_format:H:i', 'required_if:is_full_day,false'],
            'end_time' => ['nullable', 'date_format:H:i', 'required_if:is_full_day,false', 'after:start_time'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('reason') === '') {
            $this->merge(['reason' => null]);
        }
    }
}
