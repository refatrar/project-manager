<?php

namespace App\Models;

use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * One capability in Phase 7's admin-panel RBAC (e.g. `teams.manage`), now
 * backed by `spatie/laravel-permission` (TASKS.md 7.8, ADR-016). Extends
 * spatie's own model so `HasPermissions`/`config/permission.php` keep
 * working — this class adds `module`/`label`, the actual point of "module-
 * wise custom permissions": spatie's own `name` column is the raw
 * dot-notation key, not a human-readable, groupable label.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $module
 * @property string|null $label
 */
#[Fillable(['name', 'guard_name', 'module', 'label'])]
class Permission extends SpatiePermission
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;
}
