<?php

namespace App\Enums;

enum ExpenseDocumentType: string
{
    case SignedReceipt = 'signed_receipt';
    case Invoice = 'invoice';
    case PaymentProof = 'payment_proof';
    case Bill = 'bill';
    case Photo = 'photo';
    case Other = 'other';
}
