<?php

namespace App\Http\Requests\OMS;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveUserWorkScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request. `effective_from`
     * may not be backdated — a schedule change always starts today or
     * later, so history stays append-only.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'effective_from' => ['required', 'date', 'after_or_equal:today'],
            'days' => ['required', 'array', 'size:7'],
            'days.*.day_of_week' => ['required', 'integer', 'between:1,7', 'distinct'],
            'days.*.is_working_day' => ['required', 'boolean'],
            'days.*.start_time' => ['nullable', 'date_format:H:i', 'required_if:days.*.is_working_day,true'],
            'days.*.end_time' => ['nullable', 'date_format:H:i', 'required_if:days.*.is_working_day,true', 'after:days.*.start_time'],
            'days.*.break_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'days.*.capacity_hours' => ['required', 'numeric', 'min:0', 'max:24'],
        ];
    }
}
