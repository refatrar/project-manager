<?php

namespace App\Casts;

use App\Enums\TeamRole;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Membership and invitation roles are stored as a slug. The three built-in
 * slugs still come back as `TeamRole` so existing comparisons (`=== Owner`,
 * `->value`, `->label()`) keep working. Any other slug is a custom team
 * role and stays a string.
 *
 * @implements CastsAttributes<TeamRole|string|null, TeamRole|string|null>
 */
class AsTeamMembershipRole implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): TeamRole|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return TeamRole::tryFrom((string) $value) ?? (string) $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value instanceof TeamRole) {
            return $value->value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
