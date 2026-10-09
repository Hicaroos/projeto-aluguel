<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    ChartPie,
    Coins,
    Contact,
    FileSignature,
    FileText,
    House,
    LayoutGrid,
    ReceiptText,
    UserCog,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard, management } from '@/routes';
import { index as branches } from '@/routes/branches';
import { index as contractTemplates } from '@/routes/contract-templates';
import { index as expenses } from '@/routes/expenses';
import { index as leases } from '@/routes/leases';
import { index as owners } from '@/routes/owners';
import { index as payments } from '@/routes/payments';
import { index as properties } from '@/routes/properties';
import { index as team } from '@/routes/team';
import { index as tenants } from '@/routes/tenants';
import type { NavItem } from '@/types';

const can = usePermissions();

const page = usePage();

const isAgency = computed(
    () => page.props.auth.user.account?.type === 'agency',
);

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    ...(isAgency.value
        ? [
              {
                  title: 'Proprietários',
                  href: owners(),
                  icon: Contact,
              },
          ]
        : []),
    {
        title: 'Imóveis',
        href: properties(),
        icon: House,
    },
    {
        title: 'Inquilinos',
        href: tenants(),
        icon: Users,
    },
    {
        title: 'Contratos',
        href: leases(),
        icon: FileText,
    },
    ...(can.value.registerReceipts
        ? [
              {
                  title: 'Cobranças',
                  href: payments(),
                  icon: ReceiptText,
              },
          ]
        : []),
    ...(can.value.manageFinance
        ? [
              {
                  title: 'Despesas',
                  href: expenses(),
                  icon: Coins,
              },
          ]
        : []),
]);

const managementNavItems = computed<NavItem[]>(() =>
    can.value.manageAgency
        ? [
              {
                  title: 'Visão geral',
                  href: management(),
                  icon: ChartPie,
              },
              {
                  title: 'Equipe',
                  href: team(),
                  icon: UserCog,
              },
              {
                  title: 'Unidades',
                  href: branches(),
                  icon: Building2,
              },
          ]
        : [],
);

const footerNavItems = computed<NavItem[]>(() =>
    can.value.manageRentals
        ? [
              {
                  title: 'Modelos de contrato',
                  href: contractTemplates(),
                  icon: FileSignature,
              },
          ]
        : [],
);
</script>

<template>
    <Sidebar collapsible="icon" variant="floating">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain
                v-if="managementNavItems.length > 0"
                label="Gestão"
                :items="managementNavItems"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter
                v-if="footerNavItems.length > 0"
                :items="footerNavItems"
            />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
