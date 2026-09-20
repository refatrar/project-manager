<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TimeLogSource: string
{
    use HasOptions;

    case Manual = 'manual';
    case Timer = 'timer';
    case Import = 'import';
    case GitActivity = 'git_activity';
}
