<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Priority: string
{
    use HasOptions;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /**
     * Get the sort weight, where a higher number is more urgent.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }
}
