<script setup lang="ts">
import WelcomeWhatsAppButton from '@/components/welcome/WelcomeWhatsAppButton.vue';
import { salesWhatsappUrl } from '@/lib/welcome';

defineProps<{
    salesWhatsapp: string | null;
}>();

/** The plans by size of portfolio. Their prices are given over WhatsApp for now. */
const plans = [
    {
        name: 'Proprietário',
        description: 'Para quem aluga os próprios imóveis, sem imobiliária.',
    },
    {
        name: 'Imobiliária',
        description:
            'Equipe com papéis, carteira de proprietários, repasses e vitrine.',
    },
    {
        name: 'Rede de unidades',
        description: 'Várias unidades, cada equipe com a própria carteira.',
    },
];
</script>

<template>
    <section id="planos" class="scroll-mt-16 px-4 py-24 sm:px-6 lg:py-32">
        <div class="mx-auto max-w-6xl">
            <p class="text-[19px] font-semibold text-primary">Planos</p>
            <h2
                class="mt-2.5 max-w-[760px] text-[clamp(2.25rem,4.6vw,3.5rem)] leading-[1.06] font-bold tracking-[-0.035em] text-balance"
            >
                Um plano para cada tamanho de carteira.
            </h2>
            <p class="mt-4 max-w-[600px] text-[19px] text-zinc-500">
                Chame no WhatsApp para receber os valores.
            </p>

            <div class="mt-14 grid gap-5 md:grid-cols-3">
                <div
                    v-for="plan in plans"
                    :key="plan.name"
                    class="flex flex-col gap-3.5 rounded-[28px] bg-zinc-100 p-9"
                >
                    <h3 class="text-[26px] font-bold tracking-tight">
                        {{ plan.name }}
                    </h3>
                    <p class="text-zinc-500">{{ plan.description }}</p>
                    <div class="mt-auto pt-6 text-xl font-semibold">
                        Valor sob consulta
                    </div>
                    <WelcomeWhatsAppButton
                        class="self-start"
                        variant="outline"
                        size="sm"
                        :href="
                            salesWhatsappUrl(
                                salesWhatsapp,
                                `Olá, quero saber do plano ${plan.name}.`,
                            )
                        "
                    />
                </div>
            </div>
        </div>
    </section>
</template>
