<?php

namespace App\Actions\Dashboard;

use App\Actions\Payments\SummarizePayments;
use App\Enums\PropertyStatus;
use App\Models\Branch;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class SummarizeBranches
{
    public function __construct(private SummarizePayments $summarizePayments) {}

    /**
     * Summarize how the given branches are doing this month, whatever branch is selected.
     * Null summarizes the whole agency.
     *
     * @param  array<int, int>|null  $branchIds
     * @return array{expected: float, received: float, overdue: float, overdueCount: int, properties: int, rentedProperties: int, vacantProperties: int, activeLeases: int, endingLeases: int}
     */
    public function handle(?array $branchIds): array
    {
        return Branch::viewing($branchIds, function (): array {
            $payments = fn (): Builder => Payment::query()->whereHas('lease');
            $month = $this->summarizePayments->handle($payments()->dueInMonth(CarbonImmutable::today()->startOfMonth()));
            $overdue = $this->summarizePayments->handle($payments()->overdue());

            return [
                'expected' => $month['expected'],
                'received' => $month['received'],
                'overdue' => $overdue['overdue'],
                'overdueCount' => $overdue['overdue_count'],
                'properties' => Property::query()->count(),
                'rentedProperties' => Property::query()->where('status', PropertyStatus::Rented)->count(),
                'vacantProperties' => Property::query()->where('status', PropertyStatus::Available)->count(),
                'activeLeases' => Lease::query()->active()->count(),
                'endingLeases' => Lease::query()->endingWithin(60)->count(),
            ];
        });
    }
}
