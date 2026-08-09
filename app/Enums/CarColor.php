<?php

namespace App\Enums;

use Illuminate\Support\Collection;

enum CarColor: string
{
    case WHITE = 'white';
    case BLACK = 'black';
    case SILVER = 'silver';
    case GRAY = 'gray';
    case RED = 'red';
    case BLUE = 'blue';
    case GREEN = 'green';
    case YELLOW = 'yellow';
    case ORANGE = 'orange';
    case BROWN = 'brown';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function toArray(): array
    {
        return [
            self::WHITE->value => [
                'name' => 'Blanc',
                'hex' => '#F9FAFB',
            ],
            self::BLACK->value => [
                'name' => 'Noir',
                'hex' => '#1F2937',
            ],
            self::SILVER->value => [
                'name' => 'Argent',
                'hex' => '#E5E7EB',
            ],
            self::GRAY->value => [
                'name' => 'Gris',
                'hex' => '#9CA3AF',
            ],
            self::RED->value => [
                'name' => 'Rouge',
                'hex' => '#FEE2E2',
            ],
            self::BLUE->value => [
                'name' => 'Bleu',
                'hex' => '#DBEAFE',
            ],
            self::GREEN->value => [
                'name' => 'Vert',
                'hex' => '#DCFCE7',
            ],
            self::YELLOW->value => [
                'name' => 'Jaune',
                'hex' => '#FEF9C3',
            ],
            self::ORANGE->value => [
                'name' => 'Orange',
                'hex' => '#FFEDD5',
            ],
            self::BROWN->value => [
                'name' => 'Marron',
                'hex' => '#F3E8D2',
            ],
        ];
    }

    public static function forFrontend(): array
    {
        return array_map(
            fn($color) => [
                'name' => $color['name'],
                'value' => $color['value'] ?? $color['name'],
                'hex' => $color['hex'],
            ],
            array_map(
                fn($value, $key) => ['value' => $key] + $value,
                self::toArray(),
                array_keys(self::toArray())
            )
        );
    }
}
