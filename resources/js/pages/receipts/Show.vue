<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/currency';
import { formatDate, formatDocument, formatZipCode } from '@/lib/formatters';
import {
    paymentMethodLabels,
    paymentReferenceLabel,
    receiptTotalAmount,
} from '@/lib/payment-labels';
import ReceiptShareButton from '@/pages/payments/ReceiptShareButton.vue';
import { index as paymentsIndex } from '@/routes/payments';
import type { Owner, PaymentType, Property, Receipt, Tenant } from '@/types';

const props = defineProps<{
    receipt: Receipt & {
        payment: {
            id: number;
            type: PaymentType;
            description: string | null;
            reference_month: string | null;
            due_date: string;
            amount: string;
            lease: {
                id: number;
                tenant: Pick<Tenant, 'id' | 'name' | 'cpf_cnpj'>;
                property: Pick<
                    Property,
                    | 'id'
                    | 'street'
                    | 'number'
                    | 'complement'
                    | 'neighborhood'
                    | 'city'
                    | 'state'
                    | 'zip_code'
                > & { owner: Pick<Owner, 'id' | 'name' | 'cpf_cnpj'> };
            };
        };
    };
    amountInWords: string;
}>();

const page = usePage();

const tenant = computed(() => props.receipt.payment.lease.tenant);
const property = computed(() => props.receipt.payment.lease.property);
const owner = computed(() => property.value.owner);
const isPartial = computed(
    () => Number(props.receipt.amount) < Number(props.receipt.payment.amount),
);

const tenantDocumentClause = computed(() =>
    tenant.value.cpf_cnpj
        ? `, inscrito(a) no CPF/CNPJ sob o nº ${formatDocument(tenant.value.cpf_cnpj)}`
        : '',
);
const totalAmount = computed(() => receiptTotalAmount(props.receipt));
const hasLateCharges = computed(
    () => totalAmount.value > Number(props.receipt.amount),
);
const amountText = computed(
    () => `${formatCurrency(totalAmount.value)} (${props.amountInWords})`,
);
const isExtraCharge = computed(() => props.receipt.payment.type === 'extra');
const paymentDescription = computed(() => {
    if (isExtraCharge.value) {
        return isPartial.value ? 'ao pagamento parcial de' : 'a';
    }

    return isPartial.value
        ? 'ao pagamento parcial do aluguel do mês de'
        : 'ao aluguel do mês de';
});
const referenceText = computed(() =>
    isExtraCharge.value
        ? paymentReferenceLabel(props.receipt.payment)
        : paymentReferenceLabel(props.receipt.payment).toLowerCase(),
);

const fullAddress = computed(() => {
    const complement = property.value.complement
        ? `, ${property.value.complement}`
        : '';

    return `${property.value.street}, ${property.value.number}${complement} – ${property.value.neighborhood}, ${property.value.city}/${property.value.state}, CEP ${formatZipCode(property.value.zip_code)}`;
});

const issuedOn = computed(() => {
    const [year, month, day] = props.receipt.date.split('-').map(Number);

    return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'long' }).format(
        new Date(year, month - 1, day),
    );
});

function print() {
    window.print();
}
</script>

<template>
    <Head :title="`Recibo - ${tenant.name}`" />

    <div
        class="min-h-svh bg-muted/50 px-4 py-8 print:min-h-0 print:bg-white print:p-0"
    >
        <div
            class="mx-auto mb-6 flex max-w-3xl items-center justify-between gap-3 print:hidden"
        >
            <Button variant="ghost" as-child>
                <Link :href="paymentsIndex()">
                    <ArrowLeft class="size-4" />
                    Voltar para cobranças
                </Link>
            </Button>
            <div class="flex gap-2">
                <ReceiptShareButton
                    :receipt="receipt"
                    :payment="receipt.payment"
                />
                <Button @click="print">
                    <Printer class="size-4" />
                    <span class="max-sm:sr-only">Imprimir ou salvar PDF</span>
                </Button>
            </div>
        </div>

        <article
            class="mx-auto max-w-3xl rounded-xl border border-zinc-200 bg-white p-8 text-zinc-900 shadow-sm sm:p-12 print:max-w-none print:rounded-none print:border-0 print:p-0 print:shadow-none"
        >
            <header
                class="flex flex-col gap-6 border-b border-zinc-200 pb-8 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="space-y-3">
                    <div class="flex items-center gap-2 text-zinc-500">
                        <AppLogoIcon class="size-5 text-zinc-900" />
                        <span class="text-sm font-medium">{{
                            page.props.name
                        }}</span>
                    </div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Recibo de aluguel
                    </h1>
                </div>
                <div
                    class="rounded-lg border border-zinc-200 bg-zinc-50 px-5 py-3 sm:text-right"
                >
                    <p
                        class="text-xs font-medium tracking-wide text-zinc-500 uppercase"
                    >
                        Valor recebido
                    </p>
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ formatCurrency(totalAmount) }}
                    </p>
                </div>
            </header>

            <p class="mt-8 text-base leading-relaxed text-zinc-700">
                Recebi de
                <strong class="font-semibold text-zinc-900">{{
                    tenant.name
                }}</strong
                >{{ tenantDocumentClause }}, a importância de
                <strong class="font-semibold text-zinc-900">{{
                    amountText
                }}</strong
                >, referente {{ paymentDescription }}
                <strong class="font-semibold text-zinc-900">{{
                    referenceText
                }}</strong>
                do imóvel situado à {{ fullAddress
                }}<template v-if="hasLateCharges"
                    >, acrescido de multa e juros por atraso no
                    pagamento</template
                >.
            </p>

            <p class="mt-4 text-base leading-relaxed text-zinc-700">
                Pelo que dou plena e geral quitação do valor recebido.
            </p>

            <dl
                class="mt-8 grid grid-cols-2 gap-4 rounded-lg border border-zinc-200 p-5 text-sm sm:grid-cols-4"
            >
                <div>
                    <dt class="text-zinc-500">Referência</dt>
                    <dd class="font-medium">
                        {{ paymentReferenceLabel(receipt.payment) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-zinc-500">Vencimento</dt>
                    <dd class="font-medium tabular-nums">
                        {{ formatDate(receipt.payment.due_date) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-zinc-500">Data do pagamento</dt>
                    <dd class="font-medium tabular-nums">
                        {{ formatDate(receipt.date) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-zinc-500">Forma de pagamento</dt>
                    <dd class="font-medium">
                        {{
                            receipt.payment_method
                                ? paymentMethodLabels[receipt.payment_method]
                                : '—'
                        }}
                    </dd>
                </div>
                <template v-if="hasLateCharges">
                    <div>
                        <dt class="text-zinc-500">Aluguel</dt>
                        <dd class="font-medium tabular-nums">
                            {{ formatCurrency(receipt.amount) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Multa por atraso</dt>
                        <dd class="font-medium tabular-nums">
                            {{ formatCurrency(receipt.late_fee_amount) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Juros</dt>
                        <dd class="font-medium tabular-nums">
                            {{ formatCurrency(receipt.interest_amount) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Total recebido</dt>
                        <dd class="font-semibold tabular-nums">
                            {{ formatCurrency(totalAmount) }}
                        </dd>
                    </div>
                </template>
                <div v-if="isPartial" class="col-span-2 sm:col-span-4">
                    <dt class="text-zinc-500">Valor total do aluguel</dt>
                    <dd class="font-medium tabular-nums">
                        {{ formatCurrency(receipt.payment.amount) }}
                    </dd>
                </div>
                <div v-if="receipt.notes" class="col-span-2 sm:col-span-4">
                    <dt class="text-zinc-500">Observação</dt>
                    <dd class="font-medium">{{ receipt.notes }}</dd>
                </div>
            </dl>

            <p class="mt-10 text-base text-zinc-700">
                {{ property.city }}, {{ issuedOn }}.
            </p>

            <div class="mt-16 flex flex-col items-center text-center">
                <div class="w-full max-w-sm border-t border-zinc-400" />
                <p class="mt-2 font-semibold">{{ owner.name }}</p>
                <p
                    v-if="owner.cpf_cnpj"
                    class="text-sm text-zinc-500 tabular-nums"
                >
                    CPF/CNPJ {{ formatDocument(owner.cpf_cnpj) }}
                </p>
                <p class="text-sm text-zinc-500">Locador(a)</p>
            </div>
        </article>
    </div>
</template>
