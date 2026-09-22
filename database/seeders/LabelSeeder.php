<?php

namespace Database\Seeders;

use App\Models\Setup\Label;
use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Default labels for a newly created team. Unlike `ScopeSeeder`/
 * `TaskTypeSeeder` (global tables, called once from `DatabaseSeeder`),
 * `labels` is team-scoped — this runs once per team, from
 * `CreateTeam::handle()`, not from the global seeder chain.
 */
class LabelSeeder extends Seeder
{
    public function run(Team $team): void
    {
        foreach ($this->labels() as $definition) {
            $exists = Label::query()->where('team_id', $team->id)->where('name', $definition['name'])->exists();
            if ($exists) {
                continue;
            }

            // `team_id` isn't in `Label`'s mass-assignable list (the same
            // reasoning `LabelController::store()` sets it explicitly
            // rather than passing it through `firstOrCreate`'s attributes).
            $label = new Label($definition);
            $label->team_id = $team->id;
            $label->save();
        }
    }

    /**
     * @return array<int, array{name: string, color: string, description: string}>
     */
    private function labels(): array
    {
        return [
            ['name' => 'Bug', 'color' => '#DC2626', 'description' => 'Something is broken.'],
            ['name' => 'Feature', 'color' => '#2563EB', 'description' => 'New capability or user-facing functionality.'],
            ['name' => 'Enhancement', 'color' => '#0D9488', 'description' => 'Improvement to something that already works.'],
            ['name' => 'Documentation', 'color' => '#7C3AED', 'description' => 'Guides, specs, and other written deliverables.'],
            ['name' => 'Urgent', 'color' => '#EA580C', 'description' => 'Needs attention before anything else.'],
            ['name' => 'Needs Review', 'color' => '#CA8A04', 'description' => 'Waiting on someone else to weigh in.'],
        ];
    }
}
