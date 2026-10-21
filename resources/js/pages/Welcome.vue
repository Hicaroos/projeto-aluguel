<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import WelcomeFaq from '@/components/welcome/WelcomeFaq.vue';
import WelcomeFeatures from '@/components/welcome/WelcomeFeatures.vue';
import WelcomeHero from '@/components/welcome/WelcomeHero.vue';
import WelcomePlans from '@/components/welcome/WelcomePlans.vue';
import WelcomeRentMonth from '@/components/welcome/WelcomeRentMonth.vue';
import WelcomeWhatsAppButton from '@/components/welcome/WelcomeWhatsAppButton.vue';
import { salesWhatsappUrl } from '@/lib/welcome';
import { login } from '@/routes';

const props = defineProps<{
    salesWhatsapp: string | null;
}>();

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const sections = [
    { id: 'funcionalidades', label: 'Funcionalidades' },
    { id: 'como-funciona', label: 'Como funciona' },
    { id: 'planos', label: 'Planos' },
    { id: 'duvidas', label: 'Dúvidas' },
];
</script>

<template>
    <Head title="Site para imobiliárias e proprietários de imóveis" />

    <div class="min-h-screen bg-white text-[17px] leading-normal text-zinc-900">
        <header
            class="sticky top-0 z-20 border-b border-black/10 bg-white/80 backdrop-blur-xl backdrop-saturate-150"
        >
            <div
                class="mx-auto flex min-h-14 max-w-6xl items-center gap-4 px-4 py-2.5 sm:gap-7 sm:px-6"
            >
                <a
                    href="#topo"
                    class="mr-auto text-lg font-semibold tracking-tight"
                >
                    {{ appName }}
                </a>
                <nav aria-label="Seções da página" class="hidden gap-6 md:flex">
                    <a
                        v-for="section in sections"
                        :key="section.id"
                        :href="`#${section.id}`"
                        class="text-sm text-zinc-600 hover:text-zinc-900"
                    >
                        {{ section.label }}
                    </a>
                </nav>
                <Link
                    :href="login()"
                    class="text-sm text-zinc-600 hover:text-zinc-900"
                >
                    Entrar
                </Link>
                <WelcomeWhatsAppButton
                    size="sm"
                    :href="
                        salesWhatsappUrl(
                            props.salesWhatsapp,
                            'Olá, quero conhecer o sistema.',
                        )
                    "
                >
                    <span class="sm:hidden">WhatsApp</span>
                    <span class="hidden sm:inline">Falar no WhatsApp</span>
                </WelcomeWhatsAppButton>
            </div>
        </header>

        <main>
            <WelcomeHero :sales-whatsapp="props.salesWhatsapp" />

            <section class="bg-zinc-100 px-4 py-28 sm:px-6 lg:py-32">
                <p
                    class="mx-auto max-w-[980px] text-[clamp(2rem,4.8vw,3.75rem)] leading-[1.1] font-semibold tracking-[-0.035em] text-balance"
                >
                    Menos tempo conferindo planilha.
                    <span class="text-zinc-500"
                        >Mais tempo fechando negócio.</span
                    >
                </p>
            </section>

            <WelcomeRentMonth />
            <WelcomeFeatures />
            <WelcomePlans :sales-whatsapp="props.salesWhatsapp" />
            <WelcomeFaq />

            <section class="px-4 py-24 text-center sm:px-6 lg:py-32">
                <h2
                    class="mx-auto max-w-[820px] text-[clamp(2.5rem,5.6vw,4.5rem)] leading-[1.04] font-bold tracking-[-0.04em] text-balance"
                >
                    Veja funcionando antes de decidir.
                </h2>
                <p class="mx-auto mt-5 max-w-[560px] text-[21px] text-zinc-500">
                    Chame no WhatsApp e agende uma demonstração com a rotina da
                    sua imobiliária.
                </p>
                <WelcomeWhatsAppButton
                    class="mt-9"
                    :href="
                        salesWhatsappUrl(
                            props.salesWhatsapp,
                            'Olá, quero agendar uma demonstração.',
                        )
                    "
                />
            </section>
        </main>

        <footer class="border-t border-black/10 px-4 pt-7 pb-10 sm:px-6">
            <div
                class="mx-auto flex max-w-6xl flex-wrap gap-x-7 gap-y-3 text-[13px] text-zinc-500"
            >
                <span class="mr-auto"
                    >© {{ new Date().getFullYear() }} {{ appName }}</span
                >
                <Link :href="login()" class="hover:text-zinc-900">Entrar</Link>
                <a
                    :href="salesWhatsappUrl(props.salesWhatsapp, 'Olá!')"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="hover:text-zinc-900"
                >
                    WhatsApp
                </a>
            </div>
        </footer>
    </div>
</template>
