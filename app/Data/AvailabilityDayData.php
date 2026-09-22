<?php

namespace App\Data;

readonly class AvailabilityDayData
{
    public function __construct(
        public string $date,
        public float $capacityHours,
        public float $occupiedHours,
        public float $unavailableHours,
        public float $availableHours,
    ) {
        //
    }
}
