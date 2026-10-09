<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    Coins,
    FileSignature,
    FileText,
    House,
    LayoutGrid,
    ReceiptText,
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
import { dashboard } from '@/routes';
import { index as branches } from '@/routes/branches';
import { index as contractTemplates } from '@/routes/contract-templates';
import { index as expenses } from '@/routes/expenses';
import { index as leases } from '@/routes/leases';
import { index as payments } from '@/routes/payments';
import { index as properties } from '@/routes/properties';
import { index as tenants } from '@/routes/tenants';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
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
    {
        title: 'Cobranças',
        href: payments(),
        icon: ReceiptText,
    },
    {
        title: 'Despesas',
        href: expenses(),
        icon: Coins,
    },
];

const managementNavItems = computed<NavItem[]>(() =>
    page.props.auth.can.manageAgency
        ? [
              {
                  title: 'Unidades',
                  href: branches(),
                  icon: Building2,
              },
          ]
        : [],
);

const footerNavItems: NavItem[] = [
    {
        title: 'Modelos de contrato',
        href: contractTemplates(),
        icon: FileSignature,
    },
];
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
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
