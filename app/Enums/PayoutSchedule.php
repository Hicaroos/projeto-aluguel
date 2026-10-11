<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * How long after the rent is received (or due, for guaranteed rent) the owner is paid.
 */
enum PayoutSchedule: string
{
    /** A number of days later, e.g. 5 days after the tenant paid. */
    case DaysAfter = 'days_after';

    /** On a fixed day of the month, e.g. every 10th. */
    case MonthlyDay = 'monthly_day';

    /**
     * Get the date the owner should be paid what came in on the given date.
     */
    public function payoutDate(CarbonInterface $from, int $days, int $day): CarbonImmutable
    {
        $from = CarbonImmutable::parse($from)->startOfDay();

        if ($this === self::DaysAfter) {
            return $from->addDays($days);
        }

        $sameMonth = $from->day(min($day, $from->daysInMonth));

        if ($sameMonth->gte($from)) {
            return $sameMonth;
        }

        $nextMonth = $from->startOfMonth()->addMonthNoOverflow();

        return $nextMonth->day(min($day, $nextMonth->daysInMonth));
    }
}
