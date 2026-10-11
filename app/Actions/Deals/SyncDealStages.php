<?php

namespace App\Actions\Deals;

use App\Enums\DealOutcome;
use App\Models\Account;
use App\Models\DealStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncDealStages
{
    /**
     * Save the account's open stages in the given order, creating the new ones and removing the
     * missing ones, and rename the won and lost stages. Stages still holding deals are kept.
     *
     * @param  array<int, array{id?: int|null, name: string}>  $openStages
     */
    public function handle(Account $account, array $openStages, string $wonName, string $lostName): void
    {
        DB::transaction(function () use ($account, $openStages, $wonName, $lostName): void {
            $keptIds = array_filter(array_map(fn (array $stage): ?int => $stage['id'] ?? null, $openStages));

            $removed = $account->dealStages()->open()->whereNotIn('id', $keptIds)->withCount('deals')->get();

            if ($removed->contains(fn (DealStage $stage): bool => $stage->deals_count > 0)) {
                throw ValidationException::withMessages([
                    'stages' => __('Mova as negociações de uma etapa antes de removê-la.'),
                ]);
            }

            $removed->each->delete();

            foreach (array_values($openStages) as $order => $stage) {
                $attributes = ['name' => $stage['name'], 'sort_order' => $order];

                isset($stage['id'])
                    ? $account->dealStages()->open()->whereKey($stage['id'])->update($attributes)
                    : $account->dealStages()->create($attributes);
            }

            $account->dealStages()->where('outcome', DealOutcome::Won)->update(['name' => $wonName]);
            $account->dealStages()->where('outcome', DealOutcome::Lost)->update(['name' => $lostName]);
        });
    }
}
