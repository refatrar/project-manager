<?php

namespace App\Data;

readonly class CycleTimeReportData
{
    /**
     * @param  array<int, array{status: string, label: string, avgMinutes: float|null, transitions: int}>  $statusBreakdown
     */
    public function __construct(
        public ?float $avgLeadTimeMinutes,
        public ?float $avgCycleTimeMinutes,
        public int $completedTaskCount,
        public array $statusBreakdown,
    ) {
        //
    }
}
