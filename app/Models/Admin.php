<?php

namespace App\Models;

use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * A platform administrator — a completely separate Authenticatable from
 * `App\Models\User`, its own guard and session. Never a member of any team.
 * Signing in is enough; admins are not assigned roles. Team-module
 * permissions are assigned on team roles from this panel.
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
    use HasFactory;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * @return array{name: string, email: string}
     */
    public function toProfileArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
