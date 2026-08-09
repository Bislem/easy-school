<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';

    public static function getMeta(): array
    {
        return array_map(function ($case) {
            return [
                'value' => $case->value,
                'label' => $case->label(),
            ];
        }, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::COMPLETED => 'Payé',
            self::FAILED => 'Refusé',
            self::CANCELLED => 'Annulé',
            self::REFUNDED => 'Remboursé',
            self::PARTIALLY_REFUNDED => 'Partiellement remboursé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => '#FFC107', // yellow
            self::COMPLETED => '#28A745', // green
            self::FAILED => '#DC3545', // red
            self::CANCELLED => '#6C757D', // gray
            self::REFUNDED => '#007bff', // blue
            self::PARTIALLY_REFUNDED => '#fd7e14', // orange
        };
    }

    
}
