<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum MeetingType: string
{
    use HasOptions;

    case Standup = 'standup';
    case Planning = 'planning';
    case Review = 'review';
    case Retrospective = 'retrospective';
    case Client = 'client';
    case OneOnOne = 'one_on_one';
    case General = 'general';

    /**
     * Get the display label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::OneOnOne => 'One on One',
            default => ucfirst($this->value),
        };
    }
}
