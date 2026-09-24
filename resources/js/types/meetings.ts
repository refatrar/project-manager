/**
 * What the acting user may do on a meeting workspace, computed server-side
 * with `Gate::allows` in `MeetingController::show` (depends on who
 * organized the meeting and the user's project role, so it can't come from
 * the team-wide `teamAccess` list). The server policy is still the real gate.
 */
export type MeetingAbilities = {
    update: boolean;
    cancel: boolean;
    delete: boolean;
    recordMinutes: boolean;
    startTimer: boolean;
};
