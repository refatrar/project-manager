<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum MeetingStatus: string
{
    use HasOptions;

    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
