<?php

namespace App\Enums;

/**
 * The end of the funnel a closing stage stands for.
 */
enum DealOutcome: string
{
    case Won = 'won';
    case Lost = 'lost';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Won => 'Fechado',
            self::Lost => 'Perdido',
        };
    }
}
