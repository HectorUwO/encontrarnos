<?php

namespace App\Enums\Concerns;

trait HasOptions
{
    /**
     * Opciones para los filtros y selectores de la interfaz.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
