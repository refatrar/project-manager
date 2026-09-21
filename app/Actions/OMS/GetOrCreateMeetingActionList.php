<?php

namespace App\Actions\OMS;

use App\Enums\TodoListStatus;
use App\Enums\TodoListType;
use App\Models\OMS\Meeting;
use App\Models\OMS\TodoList;

class GetOrCreateMeetingActionList
{
    /**
     * Get the meeting's action item list, creating it the first time an
     * item is needed. A meeting has at most one action list — the pair is
     * unique by (meeting_id, type) in practice, enforced here rather than
     * at the database level, same as `GetOrCreateTaskChecklist`.
     */
    public function handle(Meeting $meeting): TodoList
    {
        $list = TodoList::query()
            ->where('meeting_id', $meeting->id)
            ->where('type', TodoListType::MeetingActions->value)
            ->first();

        if ($list === null) {
            $list = new TodoList([
                'project_id' => $meeting->project_id,
                'meeting_id' => $meeting->id,
                'name' => 'Action items',
                'type' => TodoListType::MeetingActions,
                'status' => TodoListStatus::Open,
            ]);
            $list->team_id = $meeting->team_id;
            $list->save();
        }

        $list->loadMissing(['items.assignee:id,name', 'items.task.project:id,code']);

        return $list;
    }
}
