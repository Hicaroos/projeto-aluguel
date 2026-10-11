<?php

namespace App\Http\Controllers;

use App\Actions\Deals\RegisterSale;
use App\Actions\Deals\UndoSale;
use App\Enums\ContractTemplateKind;
use App\Http\Controllers\Concerns\ListsSalesOptions;
use App\Http\Controllers\Concerns\ResolvesSelectedRecord;
use App\Http\Requests\SaleRequest;
use App\Models\ContractTemplate;
use App\Models\Deal;
use App\Models\Property;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    use ListsSalesOptions, ResolvesSelectedRecord;

    /**
     * Display the sales closed in the given month, with what they were worth.
     */
    public function index(Request $request): Response
    {
        $month = $this->month($request);

        $withDetails = fn (): Builder => Sale::query()->with([
            'property:id,type,street,number,complement,neighborhood,city,state,deleted_at',
            'seller:id,name,cpf_cnpj,phone,deleted_at',
            'user:id,name',
            'documents',
        ]);

        $sales = $withDetails()
            ->soldInMonth($month)
            ->latest('sold_on')
            ->latest('id')
            ->get();

        $properties = $this->dealPropertyOptions()->where('for_sale', true)->values();

        return Inertia::render('sales/Index', [
            'month' => $month->format('Y-m'),
            'sales' => $sales,
            'selected' => $this->resolveSelectedRecord($request, $withDetails()),
            'summary' => [
                'count' => $sales->count(),
                'total' => round($sales->sum(fn (Sale $sale): float => (float) $sale->price), 2),
                'commission' => round($sales->sum(fn (Sale $sale): float => (float) $sale->commission_amount), 2),
            ],
            'templates' => ContractTemplate::query()
                ->whereIn('kind', [ContractTemplateKind::SaleContract, ContractTemplateKind::DownPaymentReceipt])
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'kind', 'name', 'is_default']),
            'users' => $this->teamMemberOptions($request),
            'saleCommissionPercent' => ($request->user()->account?->isAgency() ?? false)
                ? $request->user()->account->sale_commission_percent
                : null,
            'properties' => $properties,
            'sellPropertyId' => $properties->contains('id', $request->integer('sell')) ? $request->integer('sell') : null,
        ]);
    }

    /**
     * Close the deal as a sale.
     */
    public function store(SaleRequest $request, Deal $deal, RegisterSale $registerSale): RedirectResponse
    {
        Gate::authorize('update', $deal);

        /** @var Property $property */
        $property = $deal->property;

        $registerSale->handle($property, $request->saleAttributes(), $deal);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Venda registrada. Parabéns!')]);

        return to_route('deals.index', ['show' => $deal->id]);
    }

    /**
     * Register the sale of a property straight away, for sales closed without the funnel.
     */
    public function storeDirect(SaleRequest $request, RegisterSale $registerSale): RedirectResponse
    {
        $property = $request->saleProperty() ?? abort(404);

        Gate::authorize('update', $property);

        $sale = $registerSale->handle($property, $request->saleAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Venda registrada. Parabéns!')]);

        return to_route('sales.index', ['month' => $sale->sold_on->format('Y-m'), 'show' => $sale->id]);
    }

    /**
     * Update the details of the sale, e.g. to complete the buyer details for the contract. The
     * commission follows the price and rate.
     */
    public function update(SaleRequest $request, Sale $sale): RedirectResponse
    {
        Gate::authorize('update', $sale);

        $attributes = $request->saleAttributes();
        $commissionPercent = $attributes['commission_percent'] ?? $sale->commission_percent;

        $sale->update([
            ...$attributes,
            'commission_amount' => $commissionPercent === null
                ? null
                : round((float) $attributes['price'] * (float) $commissionPercent / 100, 2),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Venda atualizada.')]);

        return to_route('sales.index', ['month' => $sale->sold_on->format('Y-m'), 'show' => $sale->id]);
    }

    /**
     * Remove a sale registered by mistake, offering the property again.
     */
    public function destroy(Sale $sale, UndoSale $undoSale): RedirectResponse
    {
        Gate::authorize('delete', $sale);

        $undoSale->handle($sale);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Venda excluída. O imóvel voltou a ficar disponível.')]);

        return to_route('sales.index', ['month' => $sale->sold_on->format('Y-m')]);
    }

    /**
     * Get the month requested through `?month=Y-m`, or the current one.
     */
    private function month(Request $request): CarbonImmutable
    {
        $month = $request->string('month')->toString();

        return preg_match('/^\d{4}-\d{2}$/', $month) === 1
            ? CarbonImmutable::createFromFormat('Y-m-d', "{$month}-01")->startOfMonth()
            : CarbonImmutable::today()->startOfMonth();
    }
}
