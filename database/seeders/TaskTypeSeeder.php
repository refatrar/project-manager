<?php

namespace Database\Seeders;

use App\Enums\TaskTypeStatus;
use App\Models\Setup\TaskType;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $createdById = User::query()->orderBy('id')->first()?->id;

        foreach ($this->taskTypes() as $taskType) {
            $record = TaskType::query()->firstOrCreate(
                ['name' => $taskType['name']],
                [
                    'description' => $taskType['description'],
                    'status' => $taskType['status'],
                ],
            );

            if ($record->wasRecentlyCreated && $createdById !== null) {
                $record->created_by = $createdById;
                $record->save();
            }
        }
    }

    /**
     * @return array<int, array{name: string, description: string, status: TaskTypeStatus}>
     */
    private function taskTypes(): array
    {
        return [
            [
                'name' => 'Feature',
                'description' => 'New product capability or user-facing functionality.',
                'status' => TaskTypeStatus::Active,
            ],
            [
                'name' => 'Bug',
                'description' => 'Defect that needs investigation and a fix.',
                'status' => TaskTypeStatus::Active,
            ],
            [
                'name' => 'Enhancement',
                'description' => 'Improvement to an existing feature or workflow.',
                'status' => TaskTypeStatus::Active,
            ],
            [
                'name' => 'Research',
                'description' => 'Spike, discovery, or technical investigation.',
                'status' => TaskTypeStatus::Active,
            ],
            [
                'name' => 'Documentation',
                'description' => 'Guides, specs, and other written deliverables.',
                'status' => TaskTypeStatus::Active,
            ],
            [
                'name' => 'Maintenance',
                'description' => 'Upgrades, chores, and operational upkeep.',
                'status' => TaskTypeStatus::Active,
            ],
            [
                'name' => 'Feature 1',
                'description' => 'New product capability or user-facing functionality.',
                'status' => TaskTypeStatus::Active,
            ],
            [
                'name' => 'Bug 1',
                'description' => 'Defect that needs investigation and a fix.',
                'status' => TaskTypeStatus::Active,
            ],
            [
                'name' => 'Enhancement 1',
                'description' => 'Improvement to an existing feature or workflow.',
                'status' => TaskTypeStatus::Active,
            ],
            [
                'name' => 'Research 1',
                'description' => 'Spike, discovery, or technical investigation.',
                'status' => TaskTypeStatus::Active,
            ],
            [
                'name' => 'Documentation 1',
                'description' => 'Guides, specs, and other written deliverables.',
                'status' => TaskTypeStatus::Active,
            ],
            [
                'name' => 'Maintenance 1',
                'description' => 'Upgrades, chores, and operational upkeep.',
                'status' => TaskTypeStatus::Active,
            ],

        ];
    }
}
