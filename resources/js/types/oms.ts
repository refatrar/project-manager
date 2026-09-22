export type ProjectStatus =
    | 'planning'
    | 'active'
    | 'on_hold'
    | 'completed'
    | 'cancelled'
    | 'archived';

export type Priority = 'low' | 'medium' | 'high' | 'critical';

export type ProjectHealth = 'on_track' | 'at_risk' | 'off_track';

export type ProjectStatusOption = { value: ProjectStatus; label: string };
export type PriorityOption = { value: Priority; label: string };
export type ProjectHealthOption = { value: ProjectHealth; label: string };

export type ProjectOwner = {
    id: number;
    name: string;
};

export type ProjectOption = {
    id: number;
    code: string;
    name: string;
};

export type Project = {
    id: number;
    code: string;
    slug: string;
    name: string;
    status: ProjectStatus;
    priority: Priority;
    health: ProjectHealth;
    progress_percentage: number;
    start_date: string | null;
    end_date: string | null;
    archived_at: string | null;
};

export type ProjectDetail = Project & {
    description: string | null;
    color: string | null;
    client_name: string | null;
    actual_start_date: string | null;
    actual_end_date: string | null;
    estimated_hours: string | null;
    budget: string | null;
    currency: string | null;
    owner: ProjectOwner | null;
};

export type ProjectModuleStatus =
    | 'planning'
    | 'in_progress'
    | 'on_hold'
    | 'completed'
    | 'cancelled';

export type ProjectModuleStatusOption = {
    value: ProjectModuleStatus;
    label: string;
};

export type ProjectMemberRole =
    | 'owner'
    | 'manager'
    | 'lead'
    | 'developer'
    | 'designer'
    | 'qa_engineer'
    | 'devops'
    | 'viewer'
    | 'client';

export type ProjectMemberStatus = 'active' | 'inactive';

export type ProjectMemberRoleOption = {
    value: ProjectMemberRole;
    label: string;
};

export type TeamMemberOption = {
    id: number;
    name: string;
    email: string;
};

export type ProjectMember = {
    id: number;
    project_id: number;
    user: TeamMemberOption;
    role: ProjectMemberRole;
    status: ProjectMemberStatus;
    allocation_percentage: number;
    hourly_rate: string | null;
    joined_on: string | null;
    left_on: string | null;
};

export type TaskStatus =
    | 'backlog'
    | 'todo'
    | 'in_progress'
    | 'blocked'
    | 'in_review'
    | 'changes_requested'
    | 'ready_for_qa'
    | 'done'
    | 'cancelled';

export type TaskStatusOption = { value: TaskStatus; label: string };

export type TaskTypeOption = {
    id: number;
    name: string;
};

export type TaskAssignee = {
    id: number;
    name: string;
};

export type TaskAssignmentRole = 'assignee' | 'reviewer' | 'qa' | 'watcher';

export type TaskAssignmentRoleOption = {
    value: TaskAssignmentRole;
    label: string;
};

export type TaskAssignment = {
    id: number;
    task_id: number;
    user: TaskAssignee;
    role: TaskAssignmentRole;
    status: string;
    allocated_hours: string | null;
};

export type TaskLabel = {
    id: number;
    name: string;
    color: string | null;
};

export type MilestoneOption = { id: number; name: string };
export type SprintOption = { id: number; name: string };

export type Task = {
    id: number;
    project_id: number;
    project_module_id: number | null;
    milestone_id: number | null;
    sprint_id: number | null;
    reference: string;
    title: string;
    status: TaskStatus;
    priority: Priority;
    position: number;
    due_at: string | null;
    taskType: TaskTypeOption;
    assignees: TaskAssignee[];
    assignments: TaskAssignment[];
    labels: TaskLabel[];
};

export type TaskDependencyType =
    | 'blocked_by'
    | 'relates_to'
    | 'duplicates'
    | 'parent_of';

export type TaskDependencyTypeOption = {
    value: TaskDependencyType;
    label: string;
};

export type TaskReference = {
    id: number;
    reference: string;
    title: string;
    status: TaskStatus;
};

export type TaskDependencyItem = {
    id: number;
    type: TaskDependencyType;
    relatedTask: TaskReference;
};

export type TaskDetail = Task & {
    description: string | null;
    review_status: 'pending' | 'approved' | 'rejected' | null;
    deployment_stage: 'development' | 'staging' | 'production' | null;
    estimated_hours: string | null;
    logged_hours: string;
    remaining_hours: string | null;
    progress_percentage: number;
    is_billable: boolean;
    starts_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    parent: TaskReference | null;
    subtasks: Task[];
    dependencies: TaskDependencyItem[];
};

export type MilestoneStatus =
    | 'pending'
    | 'in_progress'
    | 'completed'
    | 'missed'
    | 'cancelled';

export type MilestoneStatusOption = { value: MilestoneStatus; label: string };

export type Milestone = {
    id: number;
    project_id: number;
    name: string;
    description: string | null;
    status: MilestoneStatus;
    due_on: string | null;
    position: number;
    progress_percentage: number;
    is_billable: boolean;
    payment_amount: string | null;
};

export type SprintStatus = 'planned' | 'active' | 'completed' | 'cancelled';

export type SprintStatusOption = { value: SprintStatus; label: string };

export type Sprint = {
    id: number;
    project_id: number;
    name: string;
    goal: string | null;
    status: SprintStatus;
    starts_on: string;
    ends_on: string;
    capacity_hours: string | null;
    committed_hours: string | null;
};

export type ProjectModule = {
    id: number;
    project_id: number;
    parent_id: number | null;
    name: string;
    description: string | null;
    status: ProjectModuleStatus;
    priority: Priority;
    position: number;
    start_date: string | null;
    end_date: string | null;
    estimated_hours: string | null;
    progress_percentage: number;
};

export type PortfolioHealthCounts = {
    on_track: number;
    at_risk: number;
    off_track: number;
};

export type ProjectProgress = {
    totalTasks: number;
    completedTasks: number;
    inProgressTasks: number;
    blockedTasks: number;
    overdueTasks: number;
    progressPercentage: number;
};

export type Activity = {
    id: number;
    event: string;
    description: string | null;
    user: { id: number; name: string } | null;
    createdAt: string | null;
};

export type BurndownPoint = {
    date: string;
    total: number;
    completed: number;
    open: number;
};

export type StatusDuration = {
    status: TaskStatus;
    label: string;
    avgMinutes: number;
    transitions: number;
};

export type CycleTimeReport = {
    avgLeadTimeMinutes: number | null;
    avgCycleTimeMinutes: number | null;
    completedTaskCount: number;
    statusBreakdown: StatusDuration[];
};

export type TodoListType = 'custom' | 'daily' | 'task_checklist' | 'meeting_actions' | 'generated';
export type TodoListStatus = 'open' | 'completed' | 'archived';

export type TodoListTypeOption = { value: TodoListType; label: string };
export type TodoListStatusOption = { value: TodoListStatus; label: string };

export type TodoItem = {
    id: number;
    todo_list_id: number;
    task_id: number | null;
    assignee: { id: number; name: string } | null;
    task: TaskReference & { project_id: number } | null;
    title: string;
    notes: string | null;
    priority: Priority;
    is_completed: boolean;
    due_at: string | null;
    estimated_minutes: number | null;
    position: number;
};

export type TodoList = {
    id: number;
    name: string;
    description: string | null;
    type: TodoListType;
    status: TodoListStatus;
    scheduled_for: string | null;
    position: number;
    items: TodoItem[];
};

export type MeetingType =
    | 'standup'
    | 'planning'
    | 'review'
    | 'retrospective'
    | 'client'
    | 'one_on_one'
    | 'general';
export type MeetingStatus = 'scheduled' | 'in_progress' | 'completed' | 'cancelled';
export type MeetingTypeOption = { value: MeetingType; label: string };
export type MeetingStatusOption = { value: MeetingStatus; label: string };

export type MeetingAttendeeRole = 'organizer' | 'note_taker' | 'participant' | 'optional';
export type MeetingAttendanceStatus =
    | 'invited'
    | 'accepted'
    | 'declined'
    | 'tentative'
    | 'attended'
    | 'absent';
export type MeetingAttendeeRoleOption = { value: MeetingAttendeeRole; label: string };
export type MeetingAttendanceStatusOption = { value: MeetingAttendanceStatus; label: string };

export type MeetingAttendee = {
    id: number;
    meeting_id: number;
    user: { id: number; name: string; email: string } | null;
    guest_name: string | null;
    guest_email: string | null;
    role: MeetingAttendeeRole;
    attendance_status: MeetingAttendanceStatus;
};

export type Meeting = {
    id: number;
    title: string;
    type: MeetingType;
    status: MeetingStatus;
    project: ProjectOption | null;
    scheduled_start: string;
    scheduled_end: string;
};

export type MeetingDetail = Meeting & {
    sprint_id: number | null;
    agenda: string | null;
    minutes: string | null;
    decisions: string | null;
    location: string | null;
    meeting_url: string | null;
    started_at: string | null;
    ended_at: string | null;
    minutes_published_at: string | null;
    organizer: { id: number; name: string } | null;
    recorder: { id: number; name: string } | null;
};

export type MeetingTimer = {
    running: { id: number; started_at: string } | null;
    total_minutes: number;
};

export type MeetingAgendaItem = {
    id: number;
    meeting_id: number;
    title: string;
    description: string | null;
    notes: string | null;
    duration_minutes: number | null;
    position: number;
    is_discussed: boolean;
    task: TaskReference | null;
    presenter: { id: number; name: string } | null;
};

export type WorkScheduleDay = {
    id: number;
    day_of_week: number;
    is_working_day: boolean;
    start_time: string | null;
    end_time: string | null;
    break_minutes: number;
    capacity_hours: number;
};

export type WorkScheduleVersion = {
    effective_from: string;
    effective_until: string | null;
    is_current: boolean;
    days: WorkScheduleDay[];
};

export type TimeLogActivityType =
    | 'development'
    | 'design'
    | 'code_review'
    | 'qa'
    | 'meeting'
    | 'research'
    | 'deployment'
    | 'support'
    | 'admin';
export type TimeLogActivityTypeOption = { value: TimeLogActivityType; label: string };
export type TimeLogSource = 'manual' | 'timer' | 'import' | 'git_activity';

export type TimeLog = {
    id: number;
    user: { id: number; name: string };
    project_id: number | null;
    project: { id: number; code: string; name: string } | null;
    task_id: number | null;
    task: { id: number; reference: string; title: string } | null;
    meeting_id: number | null;
    description: string | null;
    activity_type: TimeLogActivityType;
    source: TimeLogSource;
    started_at: string;
    ended_at: string | null;
    duration_minutes: number;
    logged_on: string;
    is_billable: boolean;
    approval_status: ApprovalStatus;
};

export type AvailabilityDay = {
    date: string;
    capacity_hours: number;
    occupied_hours: number;
    unavailable_hours: number;
    available_hours: number;
};

export type TimesheetRow = {
    project: { id: number; code: string; name: string } | null;
    activity_type: TimeLogActivityType;
    days: Record<string, number>;
    total: number;
};

export type TimesheetStatusCounts = {
    pending: number;
    submitted: number;
    approved: number;
    rejected: number;
};

export type AvailableUser = {
    id: number;
    name: string;
    email: string;
};

export type AllocationStatus = 'planned' | 'confirmed' | 'completed' | 'cancelled';
export type AllocationStatusOption = { value: AllocationStatus; label: string };

export type ResourceAllocation = {
    id: number;
    user: { id: number; name: string };
    task_id: number | null;
    task: { id: number; number: number; title: string } | null;
    sprint_id: number | null;
    status: AllocationStatus;
    starts_on: string;
    ends_on: string;
    hours_per_day: number;
    allocation_percentage: number | null;
    notes: string | null;
};

export type TimeOffType = 'vacation' | 'sick' | 'public_holiday' | 'training' | 'personal' | 'unpaid';
export type TimeOffTypeOption = { value: TimeOffType; label: string };
export type ApprovalStatus = 'pending' | 'submitted' | 'approved' | 'rejected' | 'cancelled';

export type TimeOffRequest = {
    id: number;
    user: { id: number; name: string };
    type: TimeOffType;
    status: ApprovalStatus;
    starts_on: string;
    ends_on: string;
    is_full_day: boolean;
    start_time: string | null;
    end_time: string | null;
    total_hours: number | null;
    reason: string | null;
    approver: { id: number; name: string } | null;
    approved_at: string | null;
    decision_note: string | null;
};
