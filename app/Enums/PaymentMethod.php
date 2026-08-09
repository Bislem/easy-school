<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CREDIT_CARD = 'credit_card';
    case DEBIT_CARD = 'debit_card';
    case PAYPAL = 'paypal';
    case STRIPE = 'stripe';
    case BANK_TRANSFER = 'bank_transfer';
    case CASH = 'cash';
    case ALGERIA_POST = 'algeria_post';

    public function label(): string
    {
        return match ($this) {
            self::CREDIT_CARD => 'Carte de crédit',
            self::DEBIT_CARD => 'Carte de débit',
            self::PAYPAL => 'PayPal',
            self::STRIPE => 'Stripe',
            self::BANK_TRANSFER => 'Virement bancaire',
            self::CASH => 'Espèces',
            self::ALGERIA_POST => 'Algérie Poste',
        };
    }
}
