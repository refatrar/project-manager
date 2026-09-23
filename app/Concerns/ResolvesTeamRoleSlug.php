<?php

namespace App\Concerns;

use App\Enums\TeamRole;

trait ResolvesTeamRoleSlug
{
    public function roleSlug(): string
    {
        $role = $this->role;

        if ($role instanceof TeamRole) {
            return $role->value;
        }

        return (string) $role;
    }
}
