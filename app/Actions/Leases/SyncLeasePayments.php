<?php

namespace App\Actions\Leases;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Lease;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SyncLeasePayments
{
    /**
     * Create the lease payments that are missing up to the given month, keep the
     * upcoming pending ones in line with the lease terms and cancel the pending
     * ones that no longer fit the lease period.
     *
     * Safe to run many times: payments are unique per lease and reference month.
     */
    public function handle(Lease $lease, ?CarbonInterface $until = null): void
    {
        if (! $lease->isActive()) {
            return;
        }

        $until = CarbonImmutable::parse($until ?? today()->addMonthNoOverflow())->endOfMonth();
        $firstGeneratedMonth = CarbonImmutable::parse($lease->created_at ?? today())->startOfMonth();
        $schedule = $this->schedule($lease);

        $existingPayments = $lease->payments()
            ->rent()
            ->get()
            ->keyBy(fn (Payment $payment): string => $payment->reference_month->toDateString());

        DB::transaction(function () use ($lease, $until, $firstGeneratedMonth, $schedule, $existingPayments): void {
            foreach ($schedule as $referenceMonth => $dueDate) {
                $payment = $existingPayments->get($referenceMonth);

                if ($payment !== null) {
                    if ($payment->status === PaymentStatus::Pending && $payment->due_date->gte(today())) {
                        $payment->update(['due_date' => $dueDate, 'amount' => $lease->amount]);
                    }

                    continue;
                }

                $month = CarbonImmutable::parse($referenceMonth);

                if ($month->lt($firstGeneratedMonth) || $month->gt($until)) {
                    continue;
                }

                $lease->payments()->create([
                    'account_id' => $lease->account_id,
                    'type' => PaymentType::Rent,
                    'reference_month' => $referenceMonth,
                    'due_date' => $dueDate,
                    'amount' => $lease->amount,
                    'status' => PaymentStatus::Pending,
                ]);
            }

            $existingPayments
                ->filter(fn (Payment $payment, string $referenceMonth): bool => ! array_key_exists($referenceMonth, $schedule)
                    && $payment->status === PaymentStatus::Pending)
                ->each(fn (Payment $payment) => $payment->update(['status' => PaymentStatus::Canceled]));
        });
    }

    /**
     * Generate the upcoming payments of the account's active leases that are still missing them.
     *
     * Works as a safety net for when the scheduled generation did not run.
     */
    public function handleMissingForAccount(int $accountId): void
    {
        $nextMonth = today()->addMonthNoOverflow()->startOfMonth();

        Lease::where('account_id', $accountId)
            ->active()
            ->whereDate('end_date', '>=', $nextMonth)
            ->whereDoesntHave('payments', fn ($query) => $query->whereDate('reference_month', $nextMonth))
            ->get()
            ->each(fn (Lease $lease) => $this->handle($lease));
    }

    /**
     * Cancel the pending rent payments of the lease that are not due yet.
     *
     * Extra charges are kept: they are debts the tenant still owes after the lease ends.
     */
    public function cancelUpcoming(Lease $lease): void
    {
        $lease->payments()
            ->rent()
            ->where('status', PaymentStatus::Pending)
            ->whereDate('due_date', '>', today())
            ->update(['status' => PaymentStatus::Canceled]);
    }

    /**
     * Build the full payment schedule of the lease, keyed by reference month.
     *
     * Each month's payment is due on the lease due day of that same month, so changing
     * the due day never moves a payment to another month. The only exception is the
     * first month: when the due day comes before the lease start, it is due on the start date.
     *
     * @return array<string, string>
     */
    private function schedule(Lease $lease): array
    {
        $start = CarbonImmutable::parse($lease->start_date);
        $end = CarbonImmutable::parse($lease->end_date);
        $schedule = [];

        for ($month = 0; ($periodStart = $start->addMonthsNoOverflow($month))->lte($end); $month++) {
            $dueDate = $this->dayOfMonth($periodStart->startOfMonth(), $lease->due_day);

            $schedule[$periodStart->startOfMonth()->toDateString()] = $dueDate->max($start)->toDateString();
        }

        return $schedule;
    }

    /**
     * Get the given day of the month, capped to the last day of short months.
     */
    private function dayOfMonth(CarbonImmutable $month, int $day): CarbonImmutable
    {
        return $month->day(min($day, $month->daysInMonth));
    }
}
