<?php

namespace App\Actions\Teams;

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Every unaccepted, unexpired invitation addressed to a user's email —
 * shared between the dashboard (a user who already has a team can still
 * have pending invitations to others) and the `/start` holding page (a
 * teamless user, Phase 7, needs this to be their one way to join a team
 * without waiting on a super admin).
 */
class FindPendingInvitations
{
    /**
     * @return Collection<int, array{code: string, inviterName: string, team: array{name: string, slug: string}}>
     */
    public function handle(User $user): Collection
    {
        $email = strtolower($user->email);

        return TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation): array => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);
    }
}
