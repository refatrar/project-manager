<?php

namespace App\Models\Setup;

use App\Models\Concerns\HasAuditUsers;
use App\Models\OMS\Task;
use App\Models\Team;
use Database\Factories\Setup\LabelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string|null $color
 * @property string|null $description
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property int|null $updated_by
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Collection<int, Task> $tasks
 */
#[Fillable(['name', 'color', 'description'])]
class Label extends Model
{
    /** @use HasFactory<LabelFactory> */
    use HasAuditUsers, HasFactory;

    /**
     * Get the team that owns the label.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the tasks tagged with the label.
     *
     * @return BelongsToMany<Task, $this>
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'label_task');
    }

    /**
     * Convert the label into the payload used by Setup screens.
     *
     * @return array{id: int, name: string, color: string|null, description: string|null}
     */
    public function toSetupArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'description' => $this->description,
        ];
    }
}
