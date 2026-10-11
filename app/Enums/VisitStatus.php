<?php

namespace App\Enums;

/**
 * Where a property visit stands.
 */
enum VisitStatus: string
{
    case Scheduled = 'scheduled';
    case Done = 'done';
    case Canceled = 'canceled';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendada',
            self::Done => 'Realizada',
            self::Canceled => 'Cancelada',
        };
    }
}
