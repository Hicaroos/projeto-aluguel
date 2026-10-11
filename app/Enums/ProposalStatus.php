<?php

namespace App\Enums;

/**
 * Where a proposal stands.
 */
enum ProposalStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Countered = 'countered';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Em análise',
            self::Accepted => 'Aceita',
            self::Rejected => 'Recusada',
            self::Countered => 'Contraproposta',
        };
    }
}
