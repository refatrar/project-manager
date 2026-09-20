<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TaskAssignmentRole: string
{
    use HasOptions;

    case Assignee = 'assignee';
    case Reviewer = 'reviewer';
    case Qa = 'qa';
    case Watcher = 'watcher';

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Qa => 'QA',
            default => ucfirst($this->value),
        };
    }

    /**
     * Determine whether the role consumes the user's capacity.
     */
    public function consumesCapacity(): bool
    {
        return $this !== self::Watcher;
    }
}
