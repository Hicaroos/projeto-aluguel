<script setup lang="ts">
import { Check } from '@lucide/vue';
import WelcomeWhatsAppButton from '@/components/welcome/WelcomeWhatsAppButton.vue';
import type { BadgeTone } from '@/lib/badge-tones';
import { badgeTones } from '@/lib/badge-tones';
import { salesWhatsappUrl } from '@/lib/welcome';

defineProps<{
    salesWhatsapp: string | null;
}>();

const highlights = [
    'Multa e juros calculados',
    'Repasse e extrato em PDF',
    'Funil de venda e locação',
    'Vitrine com WhatsApp',
];

/** Example charges for the picture of the app beside the headline. */
const charges: {
    address: string;
    tenant: string;
    amount: string;
    status: string;
    tone: BadgeTone;
}[] = [
    {
        address: 'Rua das Flores, 120',
        tenant: 'João Pereira',
        amount: 'R$ 1.837,80',
        status: 'Pago',
        tone: 'success',
    },
    {
        address: 'Av. Brasil, 845, ap. 32',
        tenant: 'Marina Costa',
        amount: 'R$ 2.350,00',
        status: 'Parcial',
        tone: 'warning',
    },
    {
        address: 'Sala 204, Ed. Central',
        tenant: 'Lima Contabilidade',
        amount: 'R$ 1.200,00',
        status: 'Vence hoje',
        tone: 'neutral',
    },
];
</script>

<template>
    <section
        id="topo"
        class="overflow-hidden px-4 pt-16 pb-24 sm:px-6 lg:pt-20 lg:pb-28"
    >
        <div
            class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-12 gap-y-16"
        >
            <div class="min-w-0 flex-[1_1_460px]">
                <p
                    class="inline-block rounded-full border border-black/10 bg-white px-3.5 py-1.5 text-sm font-semibold text-zinc-600 shadow-xs"
                >
                    Do contrato ao repasse
                </p>
                <h1
                    class="mt-6 text-[clamp(2.75rem,5.6vw,4.75rem)] leading-[1.02] font-bold tracking-[-0.04em] text-balance"
                >
                    Site para imobiliárias e proprietários de imóveis
                </h1>
                <p
                    class="mt-5 max-w-[540px] text-[clamp(1.1875rem,1.8vw,1.375rem)] leading-snug text-zinc-500"
                >
                    Cobrança, multa e juros, repasse e extrato do proprietário
                    num sistema só. O funil de vendas e a vitrine de imóveis vêm
                    junto.
                </p>
                <ul
                    class="mt-7 grid max-w-[540px] gap-x-6 gap-y-3 sm:grid-cols-2"
                >
                    <li
                        v-for="highlight in highlights"
                        :key="highlight"
                        class="flex items-center gap-2.5"
                    >
                        <Check
                            class="size-[18px] shrink-0 text-primary"
                            :stroke-width="2.5"
                            aria-hidden="true"
                        />
                        {{ highlight }}
                    </li>
                </ul>
                <div class="mt-9 flex flex-wrap items-center gap-x-7 gap-y-4">
                    <WelcomeWhatsAppButton
                        :href="
                            salesWhatsappUrl(
                                salesWhatsapp,
                                'Olá, quero conhecer o sistema.',
                            )
                        "
                    />
                    <a
                        href="#como-funciona"
                        class="text-[17px] font-medium text-primary hover:underline"
                    >
                        Ver como funciona ›
                    </a>
                </div>
                <p class="mt-3.5 text-sm text-zinc-500">
                    Agende uma demonstração com a rotina da sua imobiliária.
                </p>
            </div>

            <div
                role="img"
                aria-label="Cobranças de novembro no sistema, um repasse de R$ 1.657,80 registrado e um pedido de visita que chegou pela vitrine"
                class="relative min-h-[600px] min-w-0 flex-[1_1_440px] text-zinc-900 sm:min-h-[560px]"
            >
                <div
                    class="absolute top-12 right-0 bottom-14 left-[20%] rounded-[36px] bg-primary"
                />

                <div
                    class="absolute top-28 left-0 w-[94%] rounded-[22px] bg-white p-5 text-[13px] shadow-[0_30px_70px_rgba(0,0,0,0.16),0_2px_8px_rgba(0,0,0,0.06)] sm:w-[80%]"
                >
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-[15px] font-semibold">
                            Cobranças de novembro
                        </span>
                        <span class="hidden text-zinc-500 sm:inline">
                            Imobiliária Silva
                        </span>
                    </div>
                    <div class="mt-2 divide-y divide-black/5">
                        <div
                            v-for="charge in charges"
                            :key="charge.address"
                            class="flex items-center gap-3 py-2.5"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-semibold">
                                    {{ charge.address }}
                                </div>
                                <div class="truncate text-zinc-500">
                                    {{ charge.tenant }}
                                </div>
                            </div>
                            <span class="tabular-nums">{{
                                charge.amount
                            }}</span>
                            <span
                                :class="[
                                    'rounded-full border px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap',
                                    badgeTones[charge.tone],
                                ]"
                            >
                                {{ charge.status }}
                            </span>
                        </div>
                    </div>
                    <div
                        class="mt-3 flex justify-between border-t border-black/10 pt-3"
                    >
                        <span class="text-zinc-500">Recebido no mês</span>
                        <span class="font-bold tabular-nums">R$ 48.320,00</span>
                    </div>
                </div>

                <div
                    class="absolute top-0 right-[4%] w-[62%] rounded-[18px] bg-white px-[18px] py-4 shadow-[0_18px_44px_rgba(0,0,0,0.14),0_1px_4px_rgba(0,0,0,0.06)] sm:w-[52%]"
                >
                    <div
                        class="flex items-center gap-2 text-[13px] font-semibold text-zinc-500"
                    >
                        <span
                            class="grid size-5 place-items-center rounded-full bg-primary/10"
                        >
                            <Check
                                class="size-3 text-primary"
                                :stroke-width="3"
                                aria-hidden="true"
                            />
                        </span>
                        Repasse registrado
                    </div>
                    <div
                        class="mt-1.5 text-[26px] font-bold tracking-tight tabular-nums"
                    >
                        R$ 1.657,80
                    </div>
                    <div class="text-[13px] text-zinc-500">
                        Maria Oliveira · via Pix
                    </div>
                </div>

                <div
                    class="absolute right-0 bottom-0 w-[80%] rounded-[18px] bg-white px-[18px] py-4 text-sm shadow-[0_18px_44px_rgba(0,0,0,0.14),0_1px_4px_rgba(0,0,0,0.06)] sm:w-[64%]"
                >
                    <div
                        class="text-xs font-semibold tracking-wide text-zinc-500 uppercase"
                    >
                        Pedido pela vitrine
                    </div>
                    <div class="mt-2.5 flex items-center gap-2.5">
                        <span
                            class="grid size-[34px] place-items-center rounded-full bg-zinc-100 text-[13px] font-semibold"
                        >
                            AL
                        </span>
                        <div>
                            <div class="font-semibold">Ana Lima</div>
                            <div class="text-xs text-zinc-500">Agora</div>
                        </div>
                    </div>
                    <p class="mt-2.5 rounded-xl bg-zinc-100 px-3 py-2.5">
                        Olá, vi a casa REF 0128 no site e quero visitar no
                        sábado.
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>
