<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ProjectModuleStatus: string
{
    use HasOptions;

    case Planning = 'planning';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
