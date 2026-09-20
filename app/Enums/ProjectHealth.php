<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ProjectHealth: string
{
    use HasOptions;

    case OnTrack = 'on_track';
    case AtRisk = 'at_risk';
    case OffTrack = 'off_track';
}
