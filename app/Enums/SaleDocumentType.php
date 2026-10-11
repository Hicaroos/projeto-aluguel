<?php

namespace App\Enums;

enum SaleDocumentType: string
{
    case SignedContract = 'signed_contract';
    case DownPaymentReceipt = 'down_payment_receipt';
    case Deed = 'deed';
    case PropertyRegistration = 'property_registration';
    case BuyerDocument = 'buyer_document';
    case Other = 'other';
}
