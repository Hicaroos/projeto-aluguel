<?php

namespace App\Http\Controllers\Concerns;

use Carbon\CarbonImmutable;

trait ResolvesMonthFilter
{
    /**
     * Resolve the requested month (YYYY-MM), falling back to the current month.
     */
    protected function resolveMonth(string $month): CarbonImmutable
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1) {
            return CarbonImmutable::createFromFormat('Y-m-d', "{$month}-01")->startOfDay();
        }

        return CarbonImmutable::today()->startOfMonth();
    }
}
