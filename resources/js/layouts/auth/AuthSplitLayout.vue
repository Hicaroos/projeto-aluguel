<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { FileText, House, Users } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const appName = usePage().props.name;

const highlights = [
    {
        icon: House,
        title: 'Seus imóveis organizados',
        description: 'Endereços, valores e situação de cada imóvel.',
    },
    {
        icon: Users,
        title: 'Inquilinos sempre à mão',
        description: 'Contatos e CPF/CNPJ reunidos em um só lugar.',
    },
    {
        icon: FileText,
        title: 'Contratos e recebimentos',
        description: 'Acompanhe vencimentos e pagamentos sem planilhas.',
    },
];
</script>

<template>
    <div class="grid min-h-dvh lg:grid-cols-2">
        <aside
            class="relative hidden overflow-hidden bg-linear-to-br from-teal-800 via-teal-900 to-emerald-950 p-10 text-white lg:flex lg:flex-col"
        >
            <div
                class="pointer-events-none absolute -top-32 -right-32 size-96 rounded-full bg-white/5"
            />
            <div
                class="pointer-events-none absolute -bottom-40 -left-24 size-[28rem] rounded-full bg-white/5"
            />

            <Link
                :href="home()"
                class="relative flex items-center gap-3 text-lg font-semibold"
            >
                <div
                    class="flex size-10 items-center justify-center rounded-lg bg-white/10 ring-1 ring-white/15"
                >
                    <AppLogoIcon class="size-6 text-white" />
                </div>
                {{ appName }}
            </Link>

            <div class="relative my-auto max-w-md space-y-10">
                <div class="space-y-4">
                    <h2 class="text-3xl leading-tight font-semibold">
                        Gerencie seus aluguéis com tranquilidade.
                    </h2>
                    <p class="text-white/70">
                        Tudo o que você precisa para administrar seus imóveis,
                        do cadastro ao recebimento.
                    </p>
                </div>

                <ul class="space-y-6">
                    <li
                        v-for="highlight in highlights"
                        :key="highlight.title"
                        class="flex gap-4"
                    >
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white/10 ring-1 ring-white/15"
                        >
                            <component :is="highlight.icon" class="size-5" />
                        </div>
                        <div>
                            <p class="font-medium">{{ highlight.title }}</p>
                            <p class="text-sm text-white/60">
                                {{ highlight.description }}
                            </p>
                        </div>
                    </li>
                </ul>
            </div>

            <p class="relative text-sm text-white/50">
                © {{ new Date().getFullYear() }} {{ appName }}
            </p>
        </aside>

        <main
            class="flex items-center justify-center bg-background p-6 md:p-10"
        >
            <div class="flex w-full max-w-sm flex-col gap-8">
                <Link
                    :href="home()"
                    class="flex items-center justify-center gap-2 font-semibold lg:hidden"
                >
                    <div
                        class="flex size-9 items-center justify-center rounded-md bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon class="size-5" />
                    </div>
                    {{ appName }}
                </Link>

                <div class="space-y-2 text-center lg:text-left">
                    <h1 v-if="title" class="text-2xl font-semibold">
                        {{ title }}
                    </h1>
                    <p v-if="description" class="text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </div>

                <slot />
            </div>
        </main>
    </div>
</template>
