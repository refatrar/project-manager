<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TodoListType: string
{
    use HasOptions;

    case Custom = 'custom';
    case Daily = 'daily';
    case TaskChecklist = 'task_checklist';
    case MeetingActions = 'meeting_actions';
    case Generated = 'generated';
}
