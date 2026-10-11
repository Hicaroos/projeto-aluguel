<?php

namespace App\Enums;

/**
 * Where a lead came from.
 */
enum LeadSource: string
{
    case Referral = 'referral';
    case Sign = 'sign';
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case Portal = 'portal';
    case WhatsApp = 'whatsapp';
    case Website = 'website';
    case WalkIn = 'walk_in';
    case Other = 'other';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Referral => 'Indicação',
            self::Sign => 'Placa',
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::Portal => 'Portal de imóveis',
            self::WhatsApp => 'WhatsApp',
            self::Website => 'Site',
            self::WalkIn => 'Visitou o escritório',
            self::Other => 'Outro',
        };
    }
}
