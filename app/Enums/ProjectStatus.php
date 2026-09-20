<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ProjectStatus: string
{
    use HasOptions;

    case Planning = 'planning';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Archived = 'archived';

    /**
     * Determine whether work may still be logged against the project.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Planning, self::Active, self::OnHold], true);
    }
}
