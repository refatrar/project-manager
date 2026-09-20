<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum MeetingAttendeeRole: string
{
    use HasOptions;

    case Organizer = 'organizer';
    case NoteTaker = 'note_taker';
    case Participant = 'participant';
    case Optional = 'optional';
}
