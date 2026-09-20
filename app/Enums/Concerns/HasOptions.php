<?php

namespace App\Enums\Concerns;

use Illuminate\Support\Str;

/**
 * Shared label and form-option helpers for backed string enums.
 */
trait HasOptions
{
    /**
     * Get the display label for the case.
     */
    public function label(): string
    {
        return Str::headline($this->name);
    }

    /**
     * Get the cases available in forms.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * Get the backed values for every case.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
