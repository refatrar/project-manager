<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ProjectMemberRole: string
{
    use HasOptions;

    case Owner = 'owner';
    case Manager = 'manager';
    case Lead = 'lead';
    case Developer = 'developer';
    case Designer = 'designer';
    case QaEngineer = 'qa_engineer';
    case DevOps = 'devops';
    case Viewer = 'viewer';
    case Client = 'client';

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::QaEngineer => 'QA Engineer',
            self::DevOps => 'DevOps',
            default => ucfirst($this->value),
        };
    }

    /**
     * Determine whether the role may plan work and manage members.
     */
    public function canManageProject(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Lead], true);
    }
}
