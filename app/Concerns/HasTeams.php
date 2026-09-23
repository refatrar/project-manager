<?php

namespace App\Concerns;

use App\Data\TeamPermissions;
use App\Data\UserTeam;
use App\Enums\TeamModulePermission;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use App\Services\Teams\TeamAccessControl;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

trait HasTeams
{
    /**
     * Permission names already resolved for a team during this request.
     *
     * @var array<int, list<string>>
     */
    private array $teamAccessCache = [];

    /**
     * Get all of the teams the user belongs to.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all of the teams the user owns.
     *
     * @return HasManyThrough<Team, Membership, $this>
     */
    public function ownedTeams(): HasManyThrough
    {
        return $this->hasManyThrough(
            Team::class,
            Membership::class,
            'user_id',
            'id',
            'id',
            'team_id',
        )->where('team_members.role', TeamRole::Owner->value);
    }

    /**
     * Get all of the memberships for the user.
     *
     * @return HasMany<Membership, $this>
     */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id');
    }

    /**
     * Get the user's current team.
     *
     * @return BelongsTo<Team, $this>
     */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Get the user's personal team.
     */
    public function personalTeam(): ?Team
    {
        return $this->ownedTeams()
            ->where('teams.is_personal', true)
            ->first();
    }

    /**
     * Switch to the given team.
     */
    public function switchTeam(Team $team): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $this->update(['current_team_id' => $team->id]);
        $this->setRelation('currentTeam', $team);

        URL::defaults(['current_team' => $team->slug]);

        return true;
    }

    /**
     * Determine if the user belongs to the given team.
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->exists();
    }

    /**
     * Determine if the given team is the user's current team.
     */
    public function isCurrentTeam(Team $team): bool
    {
        return $this->current_team_id === $team->id;
    }

    /**
     * Determine if the user is the owner of the given team.
     */
    public function ownsTeam(Team $team): bool
    {
        return $this->teamRole($team) === TeamRole::Owner;
    }

    /**
     * Get the user's role on the given team.
     */
    public function teamRole(Team $team): ?TeamRole
    {
        $role = $this->teamMemberships()
            ->where('team_id', $team->id)
            ->first()
            ?->role;

        return $role instanceof TeamRole ? $role : null;
    }

    /**
     * The stored membership slug, including a custom team role that is
     * not one of the three `TeamRole` cases.
     */
    public function membershipRoleSlug(Team $team): ?string
    {
        $membership = $this->teamMemberships()
            ->where('team_id', $team->id)
            ->first();

        if ($membership === null) {
            return null;
        }

        $slug = $membership->roleSlug();

        return $slug === '' ? null : $slug;
    }

    /**
     * Permission names this user holds on the team. Resolved once per
     * team for the lifetime of this model instance.
     *
     * @return list<string>
     */
    public function teamAccessList(Team $team): array
    {
        return $this->teamAccessCache[$team->id] ??= app(TeamAccessControl::class)->grantedNames($this, $team);
    }

    public function teamCan(Team $team, TeamModulePermission $permission): bool
    {
        return in_array($permission->value, $this->teamAccessList($team), true);
    }

    /**
     * Get the user's teams as a collection of UserTeam objects.
     *
     * @return Collection<int, UserTeam>
     */
    public function toUserTeams(bool $includeCurrent = false): Collection
    {
        return $this->teams()
            ->get()
            ->map(fn (Team $team) => ! $includeCurrent && $this->isCurrentTeam($team) ? null : $this->toUserTeam($team))
            ->filter()
            ->values();
    }

    /**
     * Get the user's team as a UserTeam object.
     */
    public function toUserTeam(Team $team): UserTeam
    {
        $slug = $this->membershipRoleSlug($team);

        return new UserTeam(
            id: $team->id,
            name: $team->name,
            slug: $team->slug,
            isPersonal: $team->is_personal,
            role: $slug,
            roleLabel: $slug !== null ? app(TeamAccessControl::class)->labelFor($team, $slug) : null,
            isCurrent: $this->isCurrentTeam($team),
        );
    }

    /**
     * Get the standard permissions for a team as a TeamPermissions object.
     */
    public function toTeamPermissions(Team $team): TeamPermissions
    {
        return new TeamPermissions(
            canUpdateTeam: $this->hasTeamPermission($team, TeamPermission::UpdateTeam),
            canDeleteTeam: $this->hasTeamPermission($team, TeamPermission::DeleteTeam),
            canAddMember: $this->hasTeamPermission($team, TeamPermission::AddMember),
            canUpdateMember: $this->hasTeamPermission($team, TeamPermission::UpdateMember),
            canRemoveMember: $this->hasTeamPermission($team, TeamPermission::RemoveMember),
            canCreateInvitation: $this->hasTeamPermission($team, TeamPermission::CreateInvitation),
            canCancelInvitation: $this->hasTeamPermission($team, TeamPermission::CancelInvitation),
        );
    }

    public function fallbackTeam(?Team $excluding = null): ?Team
    {
        return $this->teams()
            ->when($excluding, fn ($query) => $query->where('teams.id', '!=', $excluding->id))
            ->orderByRaw('LOWER(teams.name)')
            ->first();
    }

    /**
     * Determine if the user has the given permission on the team.
     */
    public function hasTeamPermission(Team $team, TeamPermission $permission): bool
    {
        return $this->teamCan($team, app(TeamAccessControl::class)->fromTeamPermission($permission));
    }
}
