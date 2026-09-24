import { usePage } from '@inertiajs/react';

/**
 * The current user's granted team-module permissions (the shared
 * `teamAccess` prop — `TeamModulePermission` values such as
 * `projects.create`). Use it to hide actions the server would refuse; the
 * server-side policy/middleware check is still the real gate.
 */
export function useTeamAccess(): (permission: string) => boolean {
    const teamAccess = usePage().props.teamAccess ?? [];

    return (permission: string) => teamAccess.includes(permission);
}
