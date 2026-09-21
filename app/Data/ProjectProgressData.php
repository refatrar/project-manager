<?php

namespace App\Data;

readonly class ProjectProgressData
{
    public function __construct(
        public int $totalTasks,
        public int $completedTasks,
        public int $inProgressTasks,
        public int $blockedTasks,
        public int $overdueTasks,
        public string $estimatedHours,
        public string $loggedHours,
        public string $remainingHours,
        public int $progressPercentage,
    ) {
        //
    }
}
