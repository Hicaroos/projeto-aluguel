<?php

namespace App\Http\Controllers;

use App\Actions\Deals\EnsureDefaultDealStages;
use App\Actions\Deals\MoveDeal;
use App\Actions\Deals\SyncDealStages;
use App\Http\Controllers\Concerns\ListsSalesOptions;
use App\Http\Controllers\Concerns\ResolvesSelectedRecord;
use App\Http\Requests\DealRequest;
use App\Http\Requests\DealStagesRequest;
use App\Http\Requests\MoveDealRequest;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Lead;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DealController extends Controller
{
    use ListsSalesOptions, ResolvesSelectedRecord;

    /**
     * How long closed deals stay on the board.
     */
    private const int CLOSED_DAYS_ON_BOARD = 30;

    /**
     * Display the funnel: every open deal in its stage, plus the deals closed lately.
     */
    public function index(Request $request, EnsureDefaultDealStages $ensureDefaultDealStages): Response
    {
        $account = $request->user()->account;

        abort_if($account === null, 404);

        $stages = $ensureDefaultDealStages->handle($account);

        $deals = Deal::query()
            ->withDetails()
            ->where(fn (Builder $query) => $query
                ->whereNull('closed_at')
                ->orWhere('closed_at', '>=', now()->subDays(self::CLOSED_DAYS_ON_BOARD)))
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('deal_stage_id');

        return Inertia::render('deals/Index', [
            'stages' => $stages->map(fn (DealStage $stage): array => [
                ...$stage->only(['id', 'name', 'outcome', 'sort_order']),
                'deals' => $deals->get($stage->id, collect())->values(),
            ]),
            'selected' => $this->resolveSelectedRecord($request, Deal::query()->withDetails()),
            'leads' => Lead::query()->orderBy('name')->get(['id', 'name', 'interest']),
            'properties' => $this->dealPropertyOptions(),
            'users' => $this->teamMemberOptions($request),
        ]);
    }

    /**
     * Start a deal in the first stage of the funnel, worth the price of the property unless told otherwise.
     */
    public function store(DealRequest $request, EnsureDefaultDealStages $ensureDefaultDealStages, MoveDeal $moveDeal): RedirectResponse
    {
        $account = $request->user()->account;
        $firstStage = $ensureDefaultDealStages->handle($account)->first(fn (DealStage $stage): bool => $stage->isOpen());
        $propertyId = $request->integer('property_id');
        $property = $propertyId > 0 ? Property::find($propertyId) : null;

        $deal = Deal::create([
            ...$request->validated(),
            'account_id' => $account->id,
            'deal_stage_id' => $firstStage->id,
            'value' => $request->validated('value') ?? ($request->validated('type') === 'rent' ? $property?->rent_amount : $property?->sale_price),
            'user_id' => $request->validated('user_id') ?? ($account->isAgency() ? $request->user()->id : null),
        ]);

        $moveDeal->handle($deal, $firstStage);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Negociação criada no funil.')]);

        return to_route('deals.index', ['show' => $deal->id]);
    }

    /**
     * Update the given deal.
     */
    public function update(DealRequest $request, Deal $deal): RedirectResponse
    {
        Gate::authorize('update', $deal);

        $deal->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Negociação atualizada.')]);

        return $this->backWithSelectedRecord($deal->id);
    }

    /**
     * Move the deal to another stage or place in the funnel.
     */
    public function move(MoveDealRequest $request, Deal $deal, MoveDeal $moveDeal): RedirectResponse
    {
        Gate::authorize('update', $deal);

        $moveDeal->handle($deal, $request->stage(), $request->validated('position'), $request->validated('lost_reason'));

        return back();
    }

    /**
     * Remove the given deal with its visits and proposals.
     */
    public function destroy(Deal $deal): RedirectResponse
    {
        Gate::authorize('delete', $deal);

        $deal->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Negociação removida.')]);

        return to_route('deals.index');
    }

    /**
     * Save the stages of the account's funnel.
     */
    public function updateStages(DealStagesRequest $request, SyncDealStages $syncDealStages): RedirectResponse
    {
        $syncDealStages->handle(
            $request->user()->account,
            $request->validated('stages'),
            $request->validated('won_name'),
            $request->validated('lost_name'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Etapas do funil atualizadas.')]);

        return to_route('deals.index');
    }
}
