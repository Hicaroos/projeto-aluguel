<?php

namespace App\Console\Commands;

use App\Actions\Payouts\CreditGuaranteedRent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:credit-guaranteed-rent')]
#[Description('Credit owners the guaranteed rent that has come due, whether the tenant paid or not')]
class CreditDueGuaranteedRent extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CreditGuaranteedRent $creditGuaranteedRent): int
    {
        $credited = $creditGuaranteedRent->handleDue();

        $this->info("Aluguel garantido creditado em {$credited} repasse(s).");

        return self::SUCCESS;
    }
}
