<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum MilestoneStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Missed = 'missed';
    case Cancelled = 'cancelled';
}
