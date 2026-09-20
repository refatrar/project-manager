<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum SprintStatus: string
{
    use HasOptions;

    case Planned = 'planned';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
