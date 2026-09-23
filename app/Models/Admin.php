<?php

namespace App\Models;

use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Traits\HasRoles;

/**
 * A platform administrator — a completely separate Authenticatable from
 * `App\Models\User`, its own guard and session (Phase 7, confirmed
 * decision: isolation over reuse). Never a member of any team.
 *
 * Roles/permissions are backed by `spatie/laravel-permission` (TASKS.md
 * 7.8, ADR-016), scoped to the `admin` guard only — `App\Models\User`/the
 * `web` guard never gets `HasRoles`, so `TeamRole`/`TeamPermission` (ADR-
 * 002) are completely untouched by this.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory, HasRoles;

    protected string $guard_name = 'admin';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * Determine whether this admin holds the given permission through any
     * of their roles. A stable wrapper over spatie's own
     * `hasPermissionTo()` — kept so every existing
     * `Admin::hasPermission(AdminPermission::X->value)` call site across
     * the admin controllers needed no change when this moved onto spatie.
     *
     * Unlike spatie's own `hasPermissionTo()`, this returns `false` rather
     * than throwing when the named permission doesn't exist at all (e.g.
     * a fresh, unseeded database) — the same "no" a lacking admin would
     * get, matching this method's behavior before the spatie migration.
     */
    public function hasPermission(string $key): bool
    {
        try {
            return $this->hasPermissionTo($key, 'admin');
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    /**
     * The signed-in admin's own profile. Roles are included so the
     * profile page can show them, and omitted from every write path so
     * this payload cannot be posted back as a role assignment.
     *
     * @return array{name: string, email: string, roles: list<string>}
     */
    public function toProfileArray(): array
    {
        $roles = [];

        /** @var Role $role */
        foreach ($this->roles as $role) {
            $roles[] = $role->name;
        }

        return [
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $roles,
        ];
    }
}
