<script setup lang="ts">
import { FileText } from '@lucide/vue';

/**
 * One month of a lease, step by step: R$ 1.800,00 of rent paid 3 days late, with a 2% fine and 1% a
 * month of interest (R$ 36,00 + R$ 1,80), and a 10% management fee taken before the payout.
 */
const steps: {
    date: string;
    title: string;
    text: string;
    amount?: string;
    isPayout?: boolean;
}[] = [
    {
        date: '5 nov · Vencimento',
        title: 'A cobrança já está lá',
        text: 'Gerada a partir do contrato, sem digitar nada.',
        amount: 'R$ 1.800,00',
    },
    {
        date: '8 nov · Pagamento',
        title: 'O atraso se calcula sozinho',
        text: 'Pago com 3 dias de atraso. Multa e juros entram no recebimento.',
        amount: '+ R$ 37,80',
    },
    {
        date: '10 nov · Repasse',
        title: 'O saldo já vem líquido',
        text: 'A taxa de administração sai antes. Registre o Pix e a conta do proprietário fecha.',
        amount: 'R$ 1.657,80',
        isPayout: true,
    },
    {
        date: '10 nov · Extrato',
        title: 'O proprietário entende tudo',
        text: 'Cada aluguel, taxa e repasse do mês num PDF pronto para enviar.',
    },
];
</script>

<template>
    <section
        id="como-funciona"
        class="scroll-mt-16 px-4 py-24 sm:px-6 lg:py-32"
    >
        <div class="mx-auto max-w-6xl">
            <p class="text-[19px] font-semibold text-primary">Como funciona</p>
            <h2
                class="mt-2.5 max-w-[760px] text-[clamp(2.25rem,4.6vw,3.5rem)] leading-[1.06] font-bold tracking-[-0.035em] text-balance"
            >
                Um mês de aluguel, do vencimento ao extrato.
            </h2>
            <p class="mt-4 max-w-[640px] text-[19px] text-zinc-500">
                Exemplo de um contrato de R$ 1.800,00 com multa de 2%, juros de
                1% ao mês e taxa de administração de 10%.
            </p>

            <ol
                class="mt-16 grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4"
            >
                <li
                    v-for="(step, index) in steps"
                    :key="step.date"
                    :class="[
                        'relative min-w-0 border-t-2 pt-5',
                        index === steps.length - 1
                            ? 'border-primary'
                            : 'border-zinc-300',
                    ]"
                >
                    <span
                        :class="[
                            'absolute -top-[7px] left-0 size-3 rounded-full',
                            index === steps.length - 1
                                ? 'bg-primary'
                                : 'bg-zinc-900',
                        ]"
                        aria-hidden="true"
                    />
                    <div
                        class="text-sm font-semibold tracking-wide text-zinc-500 uppercase"
                    >
                        {{ step.date }}
                    </div>
                    <h3 class="mt-2.5 text-[22px] font-semibold tracking-tight">
                        {{ step.title }}
                    </h3>
                    <p class="mt-2 text-zinc-500">{{ step.text }}</p>
                    <div
                        v-if="step.amount"
                        :class="[
                            'mt-4 text-3xl font-semibold tracking-tight tabular-nums',
                            step.isPayout && 'text-primary',
                        ]"
                    >
                        {{ step.amount }}
                    </div>
                    <div
                        v-else
                        class="mt-4 inline-flex items-center gap-2.5 rounded-xl border border-black/10 px-3.5 py-2.5 text-sm font-semibold"
                    >
                        <FileText class="size-[18px]" aria-hidden="true" />
                        extrato-novembro.pdf
                    </div>
                </li>
            </ol>

            <p class="mt-12 text-sm text-zinc-500">
                Valores de exemplo. Percentuais, prazos e regras de repasse são
                configuráveis.
            </p>
        </div>
    </section>
</template>
