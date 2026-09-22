<?php

namespace Database\Factories\OMS;

use App\Models\OMS\Attachment;
use App\Models\OMS\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $originalName = fake()->word().'.pdf';

        return [
            'team_id' => Team::factory(),
            'attachable_type' => Task::class,
            'attachable_id' => Task::factory(),
            'uploaded_by' => User::factory(),
            'disk' => 'local',
            'path' => 'attachments/'.fake()->uuid().'.pdf',
            'original_name' => $originalName,
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1024, 5_000_000),
            'checksum' => fake()->sha256(),
        ];
    }
}
