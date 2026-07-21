<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Pix = 'pix';
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case BankSlip = 'bank_slip';
    case Card = 'card';
}
