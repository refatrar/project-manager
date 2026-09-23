<?php

namespace App\Models\OMS;

use App\Models\Admin;
use Database\Factories\OMS\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A platform-wide public holiday — a specific, concrete date entered by an
 * admin (not a recurring rule). Zeroes availability for every user on that
 * date, overriding whatever `WorkSchedule`'s weekly template says.
 *
 * @property int $id
 * @property string $name
 * @property Carbon $date
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Admin|null $creator
 */
#[Fillable(['name', 'date'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Scope the query to holidays falling within the given calendar year.
     *
     * @param  Builder<Holiday>  $query
     * @return Builder<Holiday>
     */
    #[Scope]
    protected function inYear(Builder $query, int $year): Builder
    {
        return $query->whereBetween('date', ["{$year}-01-01", "{$year}-12-31"]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * @return array{id: int, name: string, date: string}
     */
    public function toListArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'date' => $this->date->toDateString(),
        ];
    }
}
