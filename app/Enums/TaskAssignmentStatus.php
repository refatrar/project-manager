<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TaskAssignmentStatus: string
{
    use HasOptions;

    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Completed = 'completed';
    case Reassigned = 'reassigned';
    case Declined = 'declined';

    /**
     * Determine whether the assignment still occupies the assignee.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Assigned, self::Accepted, self::InProgress, self::Submitted], true);
    }
}
