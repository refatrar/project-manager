<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasProjectWork;
use App\Concerns\HasTeams;
use App\Models\Git\GitCommit;
use App\Models\Git\GitIdentity;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\ResourceAllocation;
use App\Models\OMS\Task;
use App\Models\OMS\TaskAssignment;
use App\Models\OMS\TimeLog;
use App\Models\OMS\TimeOffRequest;
use App\Models\OMS\TodoList;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $profile_picture
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $avatar
 * @property-read Team|null $currentTeam
 * @property-read Collection<int, Team> $ownedTeams
 * @property-read Collection<int, Membership> $teamMemberships
 * @property-read Collection<int, Team> $teams
 * @property-read Collection<int, Project> $projects
 * @property-read Collection<int, ProjectMember> $projectMemberships
 * @property-read Collection<int, TaskAssignment> $taskAssignments
 * @property-read Collection<int, Task> $assignedTasks
 * @property-read Collection<int, TodoList> $todoLists
 * @property-read Collection<int, TimeLog> $timeLogs
 * @property-read Collection<int, TimeOffRequest> $timeOffRequests
 * @property-read Collection<int, ResourceAllocation> $resourceAllocations
 * @property-read Collection<int, GitIdentity> $gitIdentities
 * @property-read Collection<int, GitCommit> $commits
 */
#[Fillable(['name', 'email', 'password', 'current_team_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasProjectWork, HasTeams, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * `profile_picture` is not appended by default; only `avatar` — the
     * frontend never needs the raw stored path, only a ready-to-use URL.
     *
     * @var list<string>
     */
    protected $appends = ['avatar'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * The user's profile picture as a full URL, or null if they haven't
     * set one (every existing avatar display already falls back to
     * initials when this is empty).
     *
     * @return Attribute<string|null, never>
     */
    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->profile_picture !== null
                ? Storage::disk('public')->url($this->profile_picture)
                : null,
        );
    }
}
