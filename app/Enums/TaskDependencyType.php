<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TaskDependencyType: string
{
    use HasOptions;

    case BlockedBy = 'blocked_by';
    case RelatesTo = 'relates_to';
    case Duplicates = 'duplicates';
    case ParentOf = 'parent_of';
}
