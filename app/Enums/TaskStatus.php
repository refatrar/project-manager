<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Illuminate\Support\Str;

enum TaskStatus: string
{
    use HasOptions;

    case Backlog = 'backlog';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case InReview = 'in_review';
    case ChangesRequested = 'changes_requested';
    case ReadyForQa = 'ready_for_qa';
    case Done = 'done';
    case Cancelled = 'cancelled';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To Do',
            self::ReadyForQa => 'Ready for QA',
            default => Str::headline($this->name),
        };
    }

    /**
     * Determine whether the status closes the task for progress calculations.
     */
    public function isClosed(): bool
    {
        return in_array($this, [self::Done, self::Cancelled], true);
    }

    /**
     * Determine whether the status counts toward a user's occupied hours.
     */
    public function isActiveWork(): bool
    {
        return in_array($this, [self::InProgress, self::InReview, self::ChangesRequested, self::ReadyForQa], true);
    }

    /**
     * Get the statuses that represent work not yet started.
     *
     * @return array<int, self>
     */
    public static function pending(): array
    {
        return [self::Backlog, self::Todo];
    }
}
