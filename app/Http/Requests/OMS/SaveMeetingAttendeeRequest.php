<?php

namespace App\Http\Requests\OMS;

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingAttendeeRole;
use App\Models\OMS\Meeting;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMeetingAttendeeRequest extends FormRequest
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
        $meetingId = $meeting instanceof Meeting ? $meeting->id : null;
        $isCreating = ! $this->route('attendee');

        $rules = [
            'role' => ['required', Rule::enum(MeetingAttendeeRole::class)],
        ];

        if ($isCreating) {
            // A new attendee always starts "invited" — that isn't a
            // choice the inviter makes, so it's not accepted here.
            $rules['user_id'] = [
                'nullable',
                'integer',
                'required_without:guest_email',
                Rule::exists('team_members', 'user_id')->where('team_id', $teamId),
                Rule::unique('meeting_attendees', 'user_id')->where('meeting_id', $meetingId),
            ];
            $rules['guest_name'] = ['nullable', 'string', 'max:255', 'required_with:guest_email'];
            $rules['guest_email'] = ['nullable', 'email', 'max:255', 'required_without:user_id'];
        } else {
            // A registered user vs. a guest is fixed at invite time; only
            // the outcome (RSVP / attendance) changes afterward.
            $rules['attendance_status'] = ['required', Rule::enum(MeetingAttendanceStatus::class)];
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('user_id') === '' || $this->input('user_id') === 'none') {
            $this->merge(['user_id' => null]);
        }

        foreach (['guest_name', 'guest_email'] as $nullable) {
            if ($this->input($nullable) === '') {
                $this->merge([$nullable => null]);
            }
        }
    }
}
