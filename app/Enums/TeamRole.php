<?php

namespace App\Enums;

enum TeamRole: string
{
    case TeamLead = 'team_lead';
    case Member = 'member';

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::TeamLead => 'Team Lead',
            self::Member => 'Member',
        };
    }

    /**
     * Get the hierarchy level for this role.
     * Higher numbers indicate higher privileges.
     */
    public function level(): int
    {
        return match ($this) {
            self::TeamLead => 2,
            self::Member => 1,
        };
    }

    /**
     * Check if this role is at least as privileged as another role.
     */
    public function isAtLeast(TeamRole $role): bool
    {
        return $this->level() >= $role->level();
    }

    /**
     * Get the roles that can be assigned to team members (excludes TeamLead
     * — the team lead is assigned via `AssignTeamLeader`, not the normal
     * member-role-update flow).
     *
     * @return array<array{value: string, label: string}>
     */
    public static function assignable(): array
    {
        return collect(self::cases())
            ->filter(fn (self $role) => $role !== self::TeamLead)
            ->map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()])
            ->values()
            ->toArray();
    }
}
