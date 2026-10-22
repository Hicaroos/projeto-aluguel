<?php

namespace App\Http\Controllers;

use App\Actions\Messages\ComposeWhatsAppMessage;
use App\Actions\Payouts\AttachPayoutReceipt;
use App\Actions\Payouts\BuildOwnerStatement;
use App\Actions\Payouts\CreditGuaranteedRent;
use App\Actions\Payouts\RegisterOwnerPayout;
use App\Actions\Payouts\SummarizeOwnerBalances;
use App\Concerns\FormatsBrazilianNumbers;
use App\Http\Controllers\Concerns\ResolvesMonthFilter;
use App\Http\Requests\OwnerPayoutRequest;
use App\Models\Account;
use App\Models\Owner;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayout;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PayoutController extends Controller
{
    use FormatsBrazilianNumbers, ResolvesMonthFilter;

    /**
     * Display the owners with what the agency owes each of them, and the account totals, or go
     * straight to the statement of the owner picked in the owner selector.
     */
    public function index(Request $request, SummarizeOwnerBalances $summarizeOwnerBalances, CreditGuaranteedRent $creditGuaranteedRent): Response|RedirectResponse
    {
        $account = $this->agency($request);

        if (($ownerId = Owner::selectedId()) !== null) {
            return to_route('payouts.show', $ownerId);
        }

        $creditGuaranteedRent->handleDue($account->id);

        $search = $request->string('search')->trim()->toString();
        $balances = $summarizeOwnerBalances->balances();

        $owners = Owner::query()
            ->whereIn('id', OwnerLedgerEntry::query()->select('owner_id'))
            ->search($search)
            ->orderBy('name')
            ->get(['id', 'name', 'cpf_cnpj', 'pix_key'])
            ->map(fn (Owner $owner): array => [
                ...$owner->only(['id', 'name', 'cpf_cnpj', 'pix_key']),
                ...$balances[$owner->id] ?? ['available' => 0.0, 'pending' => 0.0, 'last_payout_on' => null],
            ])
            ->sortByDesc('available')
            ->values();

        return Inertia::render('payouts/Index', [
            'owners' => $owners,
            'totals' => $summarizeOwnerBalances->totals(
                array_intersect_key($balances, array_flip($owners->pluck('id')->all())),
                today(),
            ),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Display the owner's statement for the month, sticking to the owner picked in the owner selector, with the
     * WhatsApp notice of each payout in it.
     */
    public function show(Request $request, Owner $owner, BuildOwnerStatement $buildOwnerStatement, CreditGuaranteedRent $creditGuaranteedRent, ComposeWhatsAppMessage $composeWhatsAppMessage): Response|RedirectResponse
    {
        $account = $this->agency($request);
        $selectedId = Owner::selectedId();

        if ($selectedId !== null && $selectedId !== $owner->id) {
            return to_route('payouts.show', ['owner' => $selectedId, ...$request->only('month')]);
        }

        $creditGuaranteedRent->handleDue($account->id);

        $month = $this->resolveMonth($request->string('month')->toString());
        $statement = $buildOwnerStatement->handle($owner, $month);

        return Inertia::render('payouts/Show', [
            'owner' => $owner->only(['id', 'name', 'cpf_cnpj', 'pix_key']),
            'month' => $month->format('Y-m'),
            'statement' => $statement,
            'payoutMessages' => OwnerPayout::query()
                ->whereIn('id', array_filter(array_column($statement['entries'], 'owner_payout_id')))
                ->with(['owner:id,name,phone,deleted_at', 'latestSentMessage.user:id,name'])
                ->get()
                ->mapWithKeys(fn (OwnerPayout $payout): array => [$payout->id => $composeWhatsAppMessage->forPayout($payout)]),
            'properties' => $owner->properties()
                ->orderBy('properties.street')
                ->get(['properties.id', 'properties.street', 'properties.number', 'properties.complement'])
                ->map(fn ($property): array => $property->only(['id', 'street', 'number', 'complement'])),
        ]);
    }

    /**
     * Generate the owner's statement for the month as a PDF, to send it to the owner.
     */
    public function pdf(Request $request, Owner $owner, BuildOwnerStatement $buildOwnerStatement): SymfonyResponse
    {
        $account = $this->agency($request);
        $month = $this->resolveMonth($request->string('month')->toString());
        $monthName = $this->translatedDate($month, 'F \d\e Y');

        return Pdf::loadView('pdf.owner-statement', [
            'agency' => $account,
            'agencyDocument' => $this->formatDocument($account->document),
            'owner' => $owner,
            'ownerDocument' => $this->formatDocument($owner->cpf_cnpj),
            'monthName' => Str::ucfirst($monthName),
            'statement' => $buildOwnerStatement->handle($owner, $month),
            'issuedOn' => $this->translatedDate(today(), 'j \d\e F \d\e Y'),
        ])
            ->setPaper('a4')
            ->stream('extrato-'.Str::slug($owner->name).'-'.$month->format('Y-m').'.pdf');
    }

    /**
     * Record money paid to the owner, with the proof of the transfer when it is at hand.
     */
    public function store(OwnerPayoutRequest $request, Owner $owner, RegisterOwnerPayout $registerOwnerPayout, AttachPayoutReceipt $attachPayoutReceipt): RedirectResponse
    {
        $this->agency($request);

        /** @var array{amount: string, paid_on: string, payment_method?: string|null, notes?: string|null} $attributes */
        $attributes = $request->safe()->except('receipt');

        $payout = $registerOwnerPayout->handle($owner, $attributes, $request->user());

        if ($request->hasFile('receipt')) {
            /** @var UploadedFile $file */
            $file = $request->file('receipt');

            $attachPayoutReceipt->handle($payout, $file);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Repasse registrado.')]);

        return to_route('payouts.show', ['owner' => $owner->id, 'month' => $payout->paid_on->format('Y-m')]);
    }

    /**
     * Remove a payout registered by mistake, giving the amount back to the owner's balance.
     */
    public function destroy(Request $request, OwnerPayout $payout): RedirectResponse
    {
        $this->agency($request);

        Gate::authorize('delete', $payout);

        $payout->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Repasse excluído. O valor voltou para o saldo do proprietário.')]);

        return back();
    }

    /**
     * Format a date in Brazilian Portuguese, e.g. "outubro de 2026".
     */
    private function translatedDate(CarbonInterface $date, string $format): string
    {
        return $date->locale('pt_BR')->translatedFormat($format);
    }

    /**
     * Get the account of the signed in agency. Single owners receive their rent themselves.
     */
    private function agency(Request $request): Account
    {
        $account = $request->user()->account;

        abort_unless($account?->isAgency() ?? false, 404);

        return $account;
    }
}
