<?php

namespace App\Console\Commands;

use App\Actions\Leases\SyncLeasePayments;
use App\Models\Lease;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('app:generate-lease-payments')]
#[Description('Generate the upcoming payments of every active lease')]
class GenerateLeasePayments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SyncLeasePayments $syncLeasePayments): int
    {
        $leaseCount = 0;

        Lease::active()->chunkById(100, function (Collection $leases) use ($syncLeasePayments, &$leaseCount): void {
            /** @var Collection<int, Lease> $leases */
            foreach ($leases as $lease) {
                $syncLeasePayments->handle($lease);
                $leaseCount++;
            }
        });

        $this->info("Cobranças sincronizadas para {$leaseCount} contrato(s) ativo(s).");

        return self::SUCCESS;
    }
}
