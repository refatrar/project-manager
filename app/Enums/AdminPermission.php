<?php

namespace App\Enums;

/**
 * The fixed catalogue of admin-panel permission keys (Phase 7). The
 * `permissions` table is what's actually configurable — which roles hold
 * which of these — but the keys themselves are defined in code and seeded,
 * the same way `TeamPermission` is a fixed enum even though which role
 * holds which of *those* is defined in `TeamRole::permissions()`.
 */
enum AdminPermission: string
{
    case ManageTeams = 'teams.manage';
    case ManageRoles = 'roles.manage';
    case ManageAdmins = 'admins.manage';

    public function label(): string
    {
        return match ($this) {
            self::ManageTeams => 'Create teams and assign team leaders',
            self::ManageRoles => 'Manage roles and permissions',
            self::ManageAdmins => 'Manage admin accounts',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::ManageTeams => 'Teams',
            self::ManageRoles => 'Access control',
            self::ManageAdmins => 'Access control',
        };
    }
}
