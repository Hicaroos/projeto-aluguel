<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Building2, ChevronRight, Settings2, UserCog } from '@lucide/vue';
import DashboardPanel from '@/components/DashboardPanel.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { getInitials } from '@/composables/useInitials';
import { formatDate } from '@/lib/formatters';
import BranchSummaryCard from '@/pages/management/BranchSummaryCard.vue';
import { management } from '@/routes';
import { edit as editAgency } from '@/routes/agency';
import { index as branchesIndex } from '@/routes/branches';
import { index as teamIndex } from '@/routes/team';
import type { BranchSummary, ManagedBranch, TeamOverview } from '@/types';

defineProps<{
    agency: BranchSummary;
    branches: ManagedBranch[];
    team: TeamOverview;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Visão geral', href: management() }],
    },
});

function cityLine(branch: ManagedBranch): string | null {
    if (!branch.city) {
        return null;
    }

    return branch.state ? `${branch.city}/${branch.state}` : branch.city;
}

const shortcuts = [
    {
        title: 'Equipe',
        description: 'Convites, papéis e acessos',
        href: teamIndex(),
        icon: UserCog,
    },
    {
        title: 'Unidades',
        description: 'Escritórios e cidades',
        href: branchesIndex(),
        icon: Building2,
    },
    {
        title: 'Dados da imobiliária',
        description: 'CNPJ, contato e logo',
        href: editAgency(),
        icon: Settings2,
    },
];
</script>

<template>
    <Head title="Visão geral" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Visão geral"
            description="Como cada unidade está neste mês, lado a lado, independente da unidade selecionada no topo."
        />

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <BranchSummaryCard
                v-if="branches.length > 1"
                title="Toda a imobiliária"
                :subtitle="`${branches.length} unidades`"
                :summary="agency"
                is-total
            />
            <BranchSummaryCard
                v-for="branch in branches"
                :key="branch.id"
                :title="branch.name"
                :subtitle="cityLine(branch)"
                :summary="branch.summary"
                :is-inactive="!branch.is_active"
            />
        </section>

        <div class="grid gap-6 lg:grid-cols-5">
            <DashboardPanel
                title="Equipe"
                description="Quem acessou o sistema por último."
                class="lg:col-span-3"
            >
                <template #action>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="teamIndex()">
                            Ver equipe
                            <ChevronRight class="size-4" />
                        </Link>
                    </Button>
                </template>

                <dl class="grid grid-cols-3 border-b text-center">
                    <div class="p-4">
                        <dt class="text-xs text-muted-foreground">Ativos</dt>
                        <dd class="text-xl font-semibold tabular-nums">
                            {{ team.active }}
                        </dd>
                    </div>
                    <div class="border-x p-4">
                        <dt class="text-xs text-muted-foreground">
                            Convites pendentes
                        </dt>
                        <dd class="text-xl font-semibold tabular-nums">
                            {{ team.pending }}
                        </dd>
                    </div>
                    <div class="p-4">
                        <dt class="text-xs text-muted-foreground">
                            Desativados
                        </dt>
                        <dd class="text-xl font-semibold tabular-nums">
                            {{ team.inactive }}
                        </dd>
                    </div>
                </dl>

                <ul class="divide-y">
                    <li
                        v-for="member in team.recent"
                        :key="member.id"
                        class="flex items-center gap-3 px-5 py-3"
                    >
                        <Avatar class="size-8">
                            <AvatarFallback
                                class="bg-primary/10 text-xs text-primary"
                            >
                                {{ getInitials(member.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ member.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ member.role }}
                            </p>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{
                                member.last_login_at
                                    ? `Último acesso ${formatDate(member.last_login_at)}`
                                    : 'Ainda não entrou'
                            }}
                        </p>
                    </li>
                </ul>
            </DashboardPanel>

            <DashboardPanel
                title="Atalhos"
                description="Configurações da imobiliária."
                class="lg:col-span-2"
            >
                <ul class="divide-y">
                    <li v-for="shortcut in shortcuts" :key="shortcut.title">
                        <Link
                            :href="shortcut.href"
                            class="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-muted/50"
                        >
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <component :is="shortcut.icon" class="size-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium">
                                    {{ shortcut.title }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ shortcut.description }}
                                </p>
                            </div>
                            <ChevronRight
                                class="size-4 text-muted-foreground"
                            />
                        </Link>
                    </li>
                </ul>
            </DashboardPanel>
        </div>
    </div>
</template>
