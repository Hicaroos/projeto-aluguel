<?php

namespace App\Enums;

enum LeaseDocumentType: string
{
    case SignedContract = 'signed_contract';
    case MoveInInspection = 'move_in_inspection';
    case MoveOutInspection = 'move_out_inspection';
    case Amendment = 'amendment';
    case TenantDocument = 'tenant_document';
    case Other = 'other';
}
