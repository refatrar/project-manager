<?php

namespace App\Enums;

enum TaskTypeStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Get the statuses available in forms.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->values()
            ->all();
    }
}
