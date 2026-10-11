<?php

namespace App\Actions\Deals;

use App\Enums\DealOutcome;
use App\Models\Deal;
use App\Models\DealStage;
use Illuminate\Support\Facades\DB;

class MoveDeal
{
    /**
     * Move the deal to the given place of a stage, shifting the deals already there. Moving it to
     * a won or lost stage closes it, moving it back to an open stage reopens it.
     */
    public function handle(Deal $deal, DealStage $stage, ?int $position = null, ?string $lostReason = null): void
    {
        DB::transaction(function () use ($deal, $stage, $position, $lostReason): void {
            $others = Deal::where('deal_stage_id', $stage->id)
                ->whereKeyNot($deal->id)
                ->orderBy('position')
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $position = min(max($position ?? count($others), 0), count($others));
            array_splice($others, $position, 0, [$deal->id]);

            foreach ($others as $order => $id) {
                Deal::whereKey($id)->update(['position' => $order]);
            }

            $wasOpen = $deal->isOpen();

            $deal->update([
                'deal_stage_id' => $stage->id,
                'position' => $position,
                'closed_at' => $stage->isOpen() ? null : ($wasOpen ? now() : $deal->closed_at),
                'lost_reason' => $stage->outcome === DealOutcome::Lost ? $lostReason : null,
            ]);
        });
    }
}
