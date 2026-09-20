<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ProjectMemberStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Inactive = 'inactive';
}
