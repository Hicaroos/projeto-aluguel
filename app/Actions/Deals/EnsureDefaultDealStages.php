<?php

namespace App\Actions\Deals;

use App\Models\Account;
use App\Models\DealStage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EnsureDefaultDealStages
{
    /**
     * Give the account the standard funnel when it has no stages yet, and return its stages in
     * funnel order.
     *
     * @return Collection<int, DealStage>
     */
    public function handle(Account $account): Collection
    {
        DB::transaction(function () use ($account): void {
            if ($account->dealStages()->lockForUpdate()->exists()) {
                return;
            }

            foreach (DealStage::DEFAULTS as $order => $stage) {
                $account->dealStages()->create([...$stage, 'sort_order' => $order]);
            }
        });

        return $account->dealStages()->inFunnelOrder()->get();
    }
}
