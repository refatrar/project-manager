<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Illuminate\Support\Str;

enum TimeLogActivityType: string
{
    use HasOptions;

    case Development = 'development';
    case Design = 'design';
    case CodeReview = 'code_review';
    case Qa = 'qa';
    case Meeting = 'meeting';
    case Research = 'research';
    case Deployment = 'deployment';
    case Support = 'support';
    case Admin = 'admin';

    /**
     * Get the display label for the activity type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Qa => 'QA',
            default => Str::headline($this->name),
        };
    }
}
