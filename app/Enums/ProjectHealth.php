<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ProjectHealth: string
{
    use HasOptions;

    case OnTrack = 'on_track';
    case AtRisk = 'at_risk';
    case OffTrack = 'off_track';

    /**
     * Get the severity ranking, where a higher number is worse.
     */
    public function severity(): int
    {
        return match ($this) {
            self::OnTrack => 0,
            self::AtRisk => 1,
            self::OffTrack => 2,
        };
    }

    /**
     * Get whichever of the two health values is worse.
     */
    public function worse(self $other): self
    {
        return $other->severity() > $this->severity() ? $other : $this;
    }
}
