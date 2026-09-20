<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TodoListStatus: string
{
    use HasOptions;

    case Open = 'open';
    case Completed = 'completed';
    case Archived = 'archived';
}
