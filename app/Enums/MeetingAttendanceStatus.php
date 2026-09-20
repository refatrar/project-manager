<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum MeetingAttendanceStatus: string
{
    use HasOptions;

    case Invited = 'invited';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Tentative = 'tentative';
    case Attended = 'attended';
    case Absent = 'absent';
}
