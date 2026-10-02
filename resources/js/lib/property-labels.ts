import { Building2, House, LandPlot, Store } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import type { PropertyStatus, PropertyType } from '@/types';

export const propertyTypeLabels: Record<PropertyType, string> = {
    house: 'Casa',
    apartment: 'Apartamento',
    commercial: 'Comercial',
    land: 'Terreno',
};

export const propertyTypeIcons: Record<PropertyType, LucideIcon> = {
    house: House,
    apartment: Building2,
    commercial: Store,
    land: LandPlot,
};

export const propertyStatusLabels: Record<PropertyStatus, string> = {
    available: 'Disponível',
    rented: 'Alugado',
    maintenance: 'Manutenção',
    inactive: 'Inativo',
};

export const propertyStatusBadgeClasses: Record<PropertyStatus, string> = {
    available: 'border-primary/25 bg-primary/10 text-primary',
    rented: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/60 dark:text-sky-300',
    maintenance:
        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-300',
    inactive:
        'border-zinc-200 bg-zinc-100 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400',
};

export const propertyStatusDotClasses: Record<PropertyStatus, string> = {
    available: 'bg-primary',
    rented: 'bg-sky-500',
    maintenance: 'bg-amber-500',
    inactive: 'bg-zinc-400',
};
