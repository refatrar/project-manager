<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AllocationStatus: string
{
    use HasOptions;

    case Planned = 'planned';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Determine whether the allocation reserves capacity.
     */
    public function reservesCapacity(): bool
    {
        return in_array($this, [self::Planned, self::Confirmed], true);
    }
}
