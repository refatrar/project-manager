<?php

namespace App\Enums;

/**
 * Permission catalogue for the team panel. Keys are seeded onto the `web`
 * guard and assigned to roles from the admin panel. Team modules check them
 * through policies.
 * The seven team-settings keys reuse `TeamPermission`'s values so
 * `hasTeamPermission()` can delegate here without a second naming scheme.
 */
enum TeamModulePermission: string
{
    case ViewDashboard = 'dashboard.view';
    case ViewMyDay = 'my-day.view';

    case ViewProjects = 'projects.view';
    case CreateProjects = 'projects.create';
    case ViewAllProjects = 'projects.view-all';

    case ViewMeetings = 'meetings.view';
    case CreateMeetings = 'meetings.create';
    case ViewAllMeetings = 'meetings.view-all';

    case ManageTodos = 'todos.manage';

    case ViewTimeOff = 'time-off.view';
    case ManageTimeOff = 'time-off.manage';
    case DecideTimeOff = 'time-off.decide';

    case ManageTimeLogs = 'time-logs.manage';

    case ViewTimesheet = 'timesheet.view';
    case SubmitTimesheet = 'timesheet.submit';
    case DecideTimesheets = 'timesheet-approvals.decide';

    case ViewAvailability = 'availability.view';
    case ViewTeamCapacity = 'team-capacity.view';

    case ManageSetup = 'setup.manage';

    case UpdateTeam = 'team:update';
    case DeleteTeam = 'team:delete';
    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';
    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';

    public function label(): string
    {
        return match ($this) {
            self::ViewDashboard => 'View the team dashboard',
            self::ViewMyDay => 'View My Day',
            self::ViewProjects => 'View projects',
            self::CreateProjects => 'Create projects',
            self::ViewAllProjects => 'View every project on the team',
            self::ViewMeetings => 'View meetings',
            self::CreateMeetings => 'Schedule meetings',
            self::ViewAllMeetings => 'View every meeting on the team',
            self::ManageTodos => 'Manage personal to-do lists',
            self::ViewTimeOff => 'View time off',
            self::ManageTimeOff => 'Request time off',
            self::DecideTimeOff => 'Approve or reject time off',
            self::ManageTimeLogs => 'Log time',
            self::ViewTimesheet => 'View the timesheet',
            self::SubmitTimesheet => 'Submit a timesheet',
            self::DecideTimesheets => 'Approve or reject timesheets',
            self::ViewAvailability => 'Find available people',
            self::ViewTeamCapacity => 'View the team capacity heatmap',
            self::ManageSetup => 'Manage scopes, task types, and labels',
            self::UpdateTeam => 'Update team settings',
            self::DeleteTeam => 'Delete the team',
            self::AddMember => 'Add team members',
            self::UpdateMember => 'Change member roles',
            self::RemoveMember => 'Remove team members',
            self::CreateInvitation => 'Invite people to the team',
            self::CancelInvitation => 'Cancel invitations',
        };
    }

    public function module(): string
    {
        return match ($this) {
            self::ViewDashboard => 'Dashboard',
            self::ViewMyDay => 'My Day',
            self::ViewProjects, self::CreateProjects, self::ViewAllProjects => 'Projects',
            self::ViewMeetings, self::CreateMeetings, self::ViewAllMeetings => 'Meetings',
            self::ManageTodos => 'To-dos',
            self::ViewTimeOff, self::ManageTimeOff, self::DecideTimeOff => 'Time off',
            self::ManageTimeLogs => 'Time logs',
            self::ViewTimesheet, self::SubmitTimesheet, self::DecideTimesheets => 'Timesheets',
            self::ViewAvailability => 'Availability',
            self::ViewTeamCapacity => 'Team capacity',
            self::ManageSetup => 'Setup',
            self::UpdateTeam, self::DeleteTeam, self::AddMember, self::UpdateMember, self::RemoveMember, self::CreateInvitation, self::CancelInvitation => 'Team',
        };
    }
}
