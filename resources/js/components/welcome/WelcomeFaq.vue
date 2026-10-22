<script setup lang="ts">
import { Plus } from '@lucide/vue';

const questions = [
    {
        question: 'Precisa instalar alguma coisa?',
        answer: 'Não. O sistema funciona no navegador, no computador e no celular.',
    },
    {
        question: 'Serve para quem não é imobiliária?',
        answer: 'Sim. Quem aluga os próprios imóveis usa as mesmas cobranças, reajustes e extratos, sem as telas de equipe e unidades.',
    },
    {
        question: 'A multa e os juros são calculados sozinhos?',
        answer: 'Sim. Os percentuais ficam no contrato, e o valor aparece ao registrar um pagamento em atraso.',
    },
    {
        question: 'O proprietário recebe extrato?',
        answer: 'Sim. O extrato do mês sai em PDF, com cada aluguel, taxa e repasse.',
    },
    {
        question: 'Toda a equipe vê tudo?',
        answer: 'Não. Só o Administrador mexe na equipe, nas unidades e nos dados da imobiliária, e cada pessoa pode ficar restrita à própria unidade.',
    },
    {
        question: 'Como os interessados da vitrine chegam?',
        answer: 'Pelo WhatsApp da imobiliária ou por um pedido de visita, que entra direto no funil.',
    },
];
</script>

<template>
    <section
        id="duvidas"
        class="scroll-mt-16 bg-zinc-100 px-4 py-24 sm:px-6 lg:py-32"
    >
        <div class="mx-auto max-w-[820px]">
            <h2
                class="text-[clamp(2.25rem,4.6vw,3.5rem)] leading-[1.06] font-bold tracking-[-0.035em]"
            >
                Dúvidas frequentes
            </h2>
            <div class="mt-10 border-t border-zinc-300">
                <details
                    v-for="item in questions"
                    :key="item.question"
                    class="group border-b border-zinc-300 py-5"
                >
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-lg text-xl font-semibold focus-visible:ring-4 focus-visible:ring-primary/30 focus-visible:outline-none [&::-webkit-details-marker]:hidden"
                    >
                        {{ item.question }}
                        <Plus
                            class="size-5 shrink-0 text-zinc-500 transition-transform group-open:rotate-45 motion-reduce:transition-none"
                            aria-hidden="true"
                        />
                    </summary>
                    <p class="mt-3 max-w-[680px] text-zinc-500">
                        {{ item.answer }}
                    </p>
                </details>
            </div>
        </div>
    </section>
</template>
