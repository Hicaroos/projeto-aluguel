<script setup lang="ts">
import WelcomeFeatureTile from '@/components/welcome/WelcomeFeatureTile.vue';
import type { BadgeTone } from '@/lib/badge-tones';
import { badgeTones } from '@/lib/badge-tones';

/** The example rows and cards drawn in the tiles. */
const charges: {
    description: string;
    amount: string;
    status: string;
    tone: BadgeTone;
}[] = [
    {
        description: 'Aluguel de novembro · Rua das Flores, 120',
        amount: 'R$ 1.837,80',
        status: 'Pago',
        tone: 'success',
    },
    {
        description: 'Aluguel de novembro · Av. Brasil, 845',
        amount: 'R$ 2.350,00',
        status: 'Parcial',
        tone: 'warning',
    },
    {
        description: 'Conserto do portão · Rua Sete, 48',
        amount: 'R$ 380,00',
        status: 'Avulsa',
        tone: 'neutral',
    },
    {
        description: 'Aluguel de novembro · Sala 204',
        amount: 'R$ 1.200,00',
        status: 'Atrasada 3 dias',
        tone: 'danger',
    },
];

const adjustmentIndexes = ['IGP-M', 'IPCA', 'INPC', 'IVAR', 'Livre negociação'];

const statement = [
    { label: 'Aluguel', amount: '1.800,00' },
    { label: 'Multa e juros', amount: '37,80' },
    { label: 'Taxa de administração (10%)', amount: '−180,00' },
];

const stages: { name: string; deals: { lead: string; detail: string }[] }[] = [
    {
        name: 'Novo contato',
        deals: [
            { lead: 'Carla Mendes', detail: 'Casa, até R$ 2.000' },
            { lead: 'Rafael Souza', detail: 'Apartamento' },
        ],
    },
    { name: 'Visita', deals: [{ lead: 'Paula Lima', detail: 'Sábado, 10h' }] },
    {
        name: 'Proposta',
        deals: [{ lead: 'Diego Alves', detail: 'R$ 410.000' }],
    },
    {
        name: 'Fechado',
        deals: [{ lead: 'Lúcia Ramos', detail: 'Aluguel assinado' }],
    },
];

const team = [
    { initials: 'AS', name: 'Ana Silva', role: 'Administrador' },
    { initials: 'BR', name: 'Bruno Rocha', role: 'Corretor' },
    { initials: 'CF', name: 'Clara Freitas', role: 'Financeiro' },
];

const agenda = [
    { label: 'Cobranças vencendo hoje', count: 3 },
    { label: 'Pedidos da vitrine', count: 2 },
    { label: 'Repasses a fazer', count: 4 },
];
</script>

<template>
    <section
        id="funcionalidades"
        class="scroll-mt-16 bg-zinc-100 px-4 py-24 sm:px-6 lg:py-32"
    >
        <div class="mx-auto max-w-6xl">
            <p class="text-[19px] font-semibold text-primary">
                Funcionalidades
            </p>
            <h2
                class="mt-2.5 max-w-[760px] text-[clamp(2.25rem,4.6vw,3.5rem)] leading-[1.06] font-bold tracking-[-0.035em] text-balance"
            >
                Feito para a rotina inteira da imobiliária.
            </h2>

            <div class="mt-14 flex flex-wrap gap-5">
                <WelcomeFeatureTile
                    wide
                    eyebrow="Cobranças"
                    title="Quem pagou, quem atrasou e quanto falta."
                    text="Cobranças mensais e avulsas, pagamentos parciais e despesas do imóvel, com multa e juros calculados."
                >
                    <div class="divide-y divide-black/5 text-sm">
                        <div
                            v-for="charge in charges"
                            :key="charge.description"
                            class="flex flex-wrap items-center gap-x-3 gap-y-1 py-3"
                        >
                            <span class="w-full min-w-0 sm:w-auto sm:flex-1">{{
                                charge.description
                            }}</span>
                            <span class="font-semibold tabular-nums">{{
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
                </WelcomeFeatureTile>

                <WelcomeFeatureTile
                    eyebrow="Contratos"
                    title="Reajuste no mês certo."
                    text="Renovações, caução, fiador ou seguro-fiança, e o contrato gerado a partir do modelo da imobiliária."
                >
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="adjustmentIndex in adjustmentIndexes"
                            :key="adjustmentIndex"
                            class="rounded-full border border-black/10 px-3.5 py-1.5 text-sm font-semibold"
                        >
                            {{ adjustmentIndex }}
                        </span>
                    </div>
                </WelcomeFeatureTile>

                <WelcomeFeatureTile
                    eyebrow="Repasses"
                    title="O proprietário sabe o que recebe."
                    text="Taxa de administração, imóveis com mais de um dono e extrato mensal em PDF."
                >
                    <div
                        class="rounded-2xl bg-zinc-100 px-5 py-4 text-sm tabular-nums"
                    >
                        <div
                            v-for="line in statement"
                            :key="line.label"
                            class="flex justify-between py-1"
                        >
                            <span class="text-zinc-500">{{ line.label }}</span>
                            <span>{{ line.amount }}</span>
                        </div>
                        <div
                            class="mt-1.5 flex justify-between border-t border-black/10 pt-2.5 font-bold"
                        >
                            <span>Repasse</span>
                            <span class="text-primary">R$ 1.657,80</span>
                        </div>
                    </div>
                </WelcomeFeatureTile>

                <WelcomeFeatureTile
                    wide
                    eyebrow="Comercial"
                    title="Do primeiro contato à chave na mão."
                    text="Funil de venda e de locação com as etapas que a imobiliária escolher, interessados, visitas e propostas."
                >
                    <div
                        class="grid grid-cols-2 gap-2.5 text-[13px] md:grid-cols-4"
                    >
                        <div
                            v-for="(stage, index) in stages"
                            :key="stage.name"
                            class="flex flex-col gap-2 rounded-2xl bg-zinc-100 p-3"
                        >
                            <div class="font-semibold text-zinc-500">
                                {{ stage.name }} · {{ stage.deals.length }}
                            </div>
                            <div
                                v-for="deal in stage.deals"
                                :key="deal.lead"
                                :class="[
                                    'rounded-xl bg-white p-2.5',
                                    index === stages.length - 1 &&
                                        'ring-2 ring-primary',
                                ]"
                            >
                                <div class="font-semibold">{{ deal.lead }}</div>
                                <div class="text-zinc-500">
                                    {{ deal.detail }}
                                </div>
                            </div>
                        </div>
                    </div>
                </WelcomeFeatureTile>

                <WelcomeFeatureTile
                    eyebrow="Vitrine"
                    title="Um site com os imóveis da imobiliária."
                    text="Fotos, mapa e WhatsApp em cada imóvel. Pedidos de visita entram direto no funil."
                >
                    <div
                        class="overflow-hidden rounded-2xl border border-black/10 text-sm"
                    >
                        <div
                            class="h-[120px] bg-linear-160 from-emerald-100 to-emerald-200/70"
                            aria-hidden="true"
                        />
                        <div class="px-4 py-3.5">
                            <div class="text-xs text-zinc-500">
                                REF 0128 · Centro
                            </div>
                            <div class="mt-0.5 font-semibold">
                                Casa com 3 quartos para alugar
                            </div>
                            <div class="mt-1.5 font-bold tabular-nums">
                                R$ 1.800/mês
                            </div>
                        </div>
                    </div>
                </WelcomeFeatureTile>

                <WelcomeFeatureTile
                    eyebrow="Equipe e unidades"
                    title="Cada pessoa vê o que precisa."
                    text="Papéis com permissões próprias e carteiras separadas por unidade."
                >
                    <ul class="flex flex-col gap-2.5 text-sm">
                        <li
                            v-for="member in team"
                            :key="member.name"
                            class="flex items-center gap-3"
                        >
                            <span
                                class="grid size-[34px] place-items-center rounded-full bg-zinc-100 text-[13px] font-semibold"
                            >
                                {{ member.initials }}
                            </span>
                            <span class="flex-1">{{ member.name }}</span>
                            <span class="text-zinc-500">{{ member.role }}</span>
                        </li>
                    </ul>
                </WelcomeFeatureTile>

                <WelcomeFeatureTile
                    eyebrow="Painel"
                    title="O dia começa pela agenda."
                    text="Vencimentos, visitas, reajustes e repasses do dia, cada um com seu atalho."
                >
                    <ul class="flex flex-col gap-2 text-sm">
                        <li
                            v-for="item in agenda"
                            :key="item.label"
                            class="flex justify-between rounded-xl bg-zinc-100 px-3.5 py-2.5"
                        >
                            <span>{{ item.label }}</span>
                            <span class="font-bold tabular-nums">{{
                                item.count
                            }}</span>
                        </li>
                    </ul>
                </WelcomeFeatureTile>
            </div>
        </div>
    </section>
</template>
