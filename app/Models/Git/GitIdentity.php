<?php

namespace App\Models\Git;

use App\Enums\GitProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Maps a provider account to an application user so commits resolve to a person.
 *
 * @property int $id
 * @property int $user_id
 * @property GitProvider $provider
 * @property string|null $username
 * @property string|null $email
 * @property string|null $external_id
 * @property string|null $avatar_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['provider', 'username', 'email', 'external_id', 'avatar_url'])]
class GitIdentity extends Model
{
    /**
     * Get the user the identity belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => GitProvider::class,
        ];
    }
}
