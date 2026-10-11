<?php

namespace App\Http\Requests;

use App\Concerns\NormalizesBrazilianNumbers;
use App\Concerns\PersonQualificationRules;
use App\Enums\DealType;
use App\Enums\PaymentPlan;
use App\Enums\PropertyStatus;
use App\Enums\RentalOutcome;
use App\Models\Deal;
use App\Models\Property;
use App\Models\Sale;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaleRequest extends FormRequest
{
    use NormalizesBrazilianNumbers, PersonQualificationRules;

    /**
     * The buyer details sent nested under `buyer`, stored on the sale as `buyer_*` columns.
     *
     * @var list<string>
     */
    private const array BUYER_QUALIFICATION = [
        'rg',
        'nationality',
        'marital_status',
        'profession',
        'zip_code',
        'street',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
    ];

    /**
     * The property chosen for a direct sale, once looked up.
     */
    private ?Property $directSaleProperty = null;

    /**
     * Store the document and phone as digits only, however they were typed.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'buyer_document' => $this->digitsOnly($this->input('buyer_document')),
            'buyer_phone' => $this->digitsOnly($this->input('buyer_phone')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Only agencies earn commissions and name the team member who sold. What happens to a lease is
     * only decided when the sale is registered.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isAgency = $this->user()->account?->isAgency() ?? false;

        return [
            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_document' => ['nullable', 'string', self::DOCUMENT_RULE],
            'buyer_phone' => ['nullable', 'string', self::PHONE_RULE],
            'buyer_email' => ['nullable', 'string', 'email', 'max:255'],
            ...$this->qualificationRules('buyer.'),
            'price' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'down_payment' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'sold_on' => ['required', 'date', 'before_or_equal:today'],
            'deed_deadline' => ['nullable', 'date', 'after_or_equal:sold_on'],
            'payment_plan' => ['nullable', Rule::enum(PaymentPlan::class)],
            'commission_percent' => $isAgency ? ['nullable', 'numeric', 'min:0', 'max:100'] : ['exclude'],
            'user_id' => $isAgency
                ? ['nullable', 'integer', Rule::exists('users', 'id')->where('account_id', $this->user()->account_id)]
                : ['exclude'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'rental_outcome' => $this->sale() === null ? ['nullable', Rule::enum(RentalOutcome::class)] : ['exclude'],
            'property_id' => $this->isDirectSale() ? ['required', 'integer'] : ['exclude'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $deal = $this->deal();

                if ($deal === null && ! $this->isDirectSale()) {
                    return;
                }

                $errorKey = $deal === null ? 'property_id' : 'deal';

                if ($deal !== null && ($deal->type !== DealType::Sale || ! $deal->isOpen())) {
                    $validator->errors()->add('deal', __('Só negociações de venda em andamento podem ser fechadas como venda.'));

                    return;
                }

                $property = $this->saleProperty();

                if ($property === null || $property->trashed()) {
                    $validator->errors()->add($errorKey, $deal === null
                        ? __('Escolha o imóvel vendido.')
                        : __('Escolha o imóvel da negociação antes de registrar a venda.'));

                    return;
                }

                if ($property->status === PropertyStatus::Sold) {
                    $validator->errors()->add($errorKey, __('Este imóvel já foi vendido.'));

                    return;
                }

                if ($deal === null && ! $property->for_sale) {
                    $validator->errors()->add('property_id', __('Este imóvel não está à venda.'));

                    return;
                }

                if ($property->hasActiveLease() && $this->enum('rental_outcome', RentalOutcome::class) === null) {
                    $validator->errors()->add('rental_outcome', __('Este imóvel está alugado. Escolha o que acontece com o contrato de aluguel.'));
                }
            },
        ];
    }

    /**
     * Get the sale attributes, with the nested buyer details as `buyer_*` columns.
     *
     * @return array<string, mixed>
     */
    public function saleAttributes(): array
    {
        $validated = $this->safe()->except(['buyer', 'property_id']);
        $buyer = $this->validated('buyer', []);

        foreach (self::BUYER_QUALIFICATION as $field) {
            $validated["buyer_{$field}"] = $buyer[$field] ?? null;
        }

        return $validated;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'buyer_name' => __('nome do comprador'),
            'buyer_document' => __('CPF/CNPJ do comprador'),
            'buyer_phone' => __('celular do comprador'),
            'buyer_email' => __('e-mail do comprador'),
            'price' => __('valor da venda'),
            'down_payment' => __('sinal'),
            'sold_on' => __('data da venda'),
            'deed_deadline' => __('prazo da escritura'),
            'commission_percent' => __('comissão'),
            'user_id' => __('corretor'),
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'down_payment.lte' => __('O sinal não pode passar do valor da venda.'),
            'deed_deadline.after_or_equal' => __('O prazo da escritura não pode ser antes da data da venda.'),
            ...$this->brazilianNumberMessages(documents: ['buyer_document'], phones: ['buyer_phone']),
        ];
    }

    /**
     * Get the deal being closed, when registering a sale.
     */
    public function deal(): ?Deal
    {
        $deal = $this->route('deal');

        return $deal instanceof Deal ? $deal : null;
    }

    /**
     * Get the sale being updated, if any.
     */
    public function sale(): ?Sale
    {
        $sale = $this->route('sale');

        return $sale instanceof Sale ? $sale : null;
    }

    /**
     * Determine whether the sale is registered straight for a property, without a deal.
     */
    public function isDirectSale(): bool
    {
        return $this->deal() === null && $this->sale() === null;
    }

    /**
     * Get the property being sold: the deal's, or the one chosen for a direct sale among those
     * the user can see.
     */
    public function saleProperty(): ?Property
    {
        $deal = $this->deal();

        if ($deal !== null) {
            return $deal->property;
        }

        if (! $this->isDirectSale() || ! is_numeric($this->input('property_id'))) {
            return null;
        }

        return $this->directSaleProperty ??= Property::find($this->integer('property_id'));
    }
}
