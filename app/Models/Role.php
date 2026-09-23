<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A team-module role backed by `spatie/laravel-permission`. Roles use the
 * `web` guard and are global (`team_id` null). The admin panel assigns
 * their permissions (ADR-017). Extends spatie's own model rather than
 * replacing it, so `config/permission.php` keeps working. `is_system`
 * protects Owner, Admin, and Member from deletion. `slug` is what a team
 * membership stores.
 *
 * @property int $id
 * @property int|null $team_id
 * @property string $name
 * @property string $guard_name
 * @property string|null $slug
 * @property string|null $description
 * @property bool $is_system
 */
#[Fillable(['name', 'guard_name', 'slug', 'description', 'is_system', 'team_id'])]
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

    /**
     * Unused. Team-module roles are global, so `team_id` stays null.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
