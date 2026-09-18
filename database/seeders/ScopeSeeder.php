<?php

namespace Database\Seeders;

use App\Enums\ScopeStatus;
use App\Models\Setup\Scope;
use App\Models\User;
use Illuminate\Database\Seeder;

class ScopeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $createdById = User::query()->orderBy('id')->first()?->id;

        foreach ($this->scopes() as $scope) {
            $record = Scope::query()->firstOrCreate(
                ['name' => $scope['name']],
                [
                    'description' => $scope['description'],
                    'status' => $scope['status'],
                ],
            );

            if ($record->wasRecentlyCreated && $createdById !== null) {
                $record->created_by = $createdById;
                $record->save();
            }
        }
    }

    /**
     * @return array<int, array{name: string, description: string, status: ScopeStatus}>
     */
    private function scopes(): array
    {
        return [
            [
                'name' => 'Web Application',
                'description' => 'Browser-based features and customer-facing web work.',
                'status' => ScopeStatus::Active,
            ],
            [
                'name' => 'Mobile Application',
                'description' => 'Native or hybrid mobile app features.',
                'status' => ScopeStatus::Active,
            ],
            [
                'name' => 'API Integration',
                'description' => 'External service integrations and API endpoints.',
                'status' => ScopeStatus::Active,
            ],
            [
                'name' => 'Admin Panel',
                'description' => 'Internal administration and configuration tools.',
                'status' => ScopeStatus::Active,
            ],
            [
                'name' => 'Reporting',
                'description' => 'Dashboards, exports, and operational reports.',
                'status' => ScopeStatus::Active,
            ],
        ];
    }
}
