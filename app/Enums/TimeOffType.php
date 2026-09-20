<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TimeOffType: string
{
    use HasOptions;

    case Vacation = 'vacation';
    case Sick = 'sick';
    case PublicHoliday = 'public_holiday';
    case Training = 'training';
    case Personal = 'personal';
    case Unpaid = 'unpaid';
}
