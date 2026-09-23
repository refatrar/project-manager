<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A configurable, admin-panel-manageable role (Phase 7's RBAC — governs the
 * admin panel and team-provisioning process only, not `TeamRole`), now
 * backed by `spatie/laravel-permission` (TASKS.md 7.8, ADR-016). Extends
 * spatie's own model rather than replacing it, so `HasRoles`/`config/
 * permission.php` keep working — this class only adds the columns this
 * app's admin panel needs on top: `is_system` (protects the seeded "Super
 * Admin" role from edits/deletion, the same invariant Phase 7 always had),
 * `slug` and `description` (both already part of the panel's UI/API
 * contract before this migration).
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $slug
 * @property string|null $description
 * @property bool $is_system
 */
#[Fillable(['name', 'guard_name', 'slug', 'description', 'is_system'])]
class Role extends SpatieRole
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    /**
     * Every `Admin` holding this role. An alias over spatie's own
     * `users()` (it resolves the right model from the role's
     * `guard_name` — `admin` maps to `App\Models\Admin` via
     * `config('auth.providers.admins.model')`) — named for what this
     * app's roles actually hold, since "users" would be misleading here.
     *
     * @return BelongsToMany<Admin, $this>
     */
    public function admins(): BelongsToMany
    {
        return $this->users();
    }
}
