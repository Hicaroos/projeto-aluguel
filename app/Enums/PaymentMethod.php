<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Pix = 'pix';
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case BankSlip = 'bank_slip';
    case Card = 'card';

    /**
     * Taken from the lease deposit when it is settled; never chosen by hand.
     */
    case Deposit = 'deposit';

    /**
     * Get the label printed on receipts.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pix => 'Pix',
            self::Cash => 'Dinheiro',
            self::BankTransfer => 'Transferência',
            self::BankSlip => 'Boleto',
            self::Card => 'Cartão',
            self::Deposit => 'Caução',
        };
    }
}
