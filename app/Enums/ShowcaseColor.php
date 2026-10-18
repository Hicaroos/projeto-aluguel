<?php

namespace App\Enums;

/**
 * The accent colors a showcase can use. A short, curated list, so every showcase stays readable
 * and elegant whatever the account picks.
 */
enum ShowcaseColor: string
{
    case Forest = 'forest';
    case Ocean = 'ocean';
    case Wine = 'wine';
    case Clay = 'clay';
    case Olive = 'olive';
    case Graphite = 'graphite';

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::Forest => 'Verde',
            self::Ocean => 'Azul',
            self::Wine => 'Vinho',
            self::Clay => 'Terracota',
            self::Olive => 'Oliva',
            self::Graphite => 'Grafite',
        };
    }

    /**
     * Get the accent color, dark enough for white text on it.
     */
    public function hex(): string
    {
        return match ($this) {
            self::Forest => '#1f5a4a',
            self::Ocean => '#1d4b73',
            self::Wine => '#6b2737',
            self::Clay => '#8a4426',
            self::Olive => '#4c5a26',
            self::Graphite => '#2b3035',
        };
    }

    /**
     * Get the darker shade used on hover.
     */
    public function strongHex(): string
    {
        return match ($this) {
            self::Forest => '#174638',
            self::Ocean => '#153a59',
            self::Wine => '#541d2b',
            self::Clay => '#6e351d',
            self::Olive => '#3b461d',
            self::Graphite => '#1d2125',
        };
    }

    /**
     * Get a light wash of the color, for notes and highlights.
     */
    public function washHex(): string
    {
        return match ($this) {
            self::Forest => '#e3ebe6',
            self::Ocean => '#e2e9f0',
            self::Wine => '#f0e3e6',
            self::Clay => '#f2e6df',
            self::Olive => '#e9ecdf',
            self::Graphite => '#e6e8ea',
        };
    }
}
