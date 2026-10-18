import {
    Building2,
    Briefcase,
    House,
    LandPlot,
    Store,
    Trees,
    Warehouse,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import type {
    Property,
    PropertyAmenity,
    PropertyPurpose,
    PropertyStatus,
    PropertyType,
} from '@/types';

export const propertyTypeLabels: Record<PropertyType, string> = {
    house: 'Casa',
    apartment: 'Apartamento',
    commercial: 'Comercial',
    office: 'Sala comercial',
    warehouse: 'Galpão',
    land: 'Terreno',
    farm: 'Chácara/Sítio',
};

export const propertyTypeIcons: Record<PropertyType, LucideIcon> = {
    house: House,
    apartment: Building2,
    commercial: Store,
    office: Briefcase,
    warehouse: Warehouse,
    land: LandPlot,
    farm: Trees,
};

/** What the property itself may offer, in the order the form lists it. */
export const propertyAmenityLabels: Partial<Record<PropertyAmenity, string>> = {
    air_conditioning: 'Ar-condicionado',
    built_in_wardrobes: 'Armários planejados',
    furnished: 'Mobiliado',
    balcony: 'Varanda',
    backyard: 'Quintal',
    service_area: 'Área de serviço',
    barbecue: 'Churrasqueira',
    gourmet_area: 'Área gourmet',
    pool: 'Piscina',
    office: 'Escritório',
    closet: 'Closet',
    solar_energy: 'Energia solar',
    water_tank: 'Cisterna',
    well: 'Poço artesiano',
    electric_gate: 'Portão eletrônico',
    security_cameras: 'Câmeras de segurança',
    pets_allowed: 'Aceita animais',
};

/** What the condominium may offer, in the order the form lists it. */
export const condoAmenityLabels: Partial<Record<PropertyAmenity, string>> = {
    gated_community: 'Condomínio fechado',
    concierge: 'Portaria 24 horas',
    elevator: 'Elevador',
    condo_pool: 'Piscina',
    gym: 'Academia',
    party_room: 'Salão de festas',
    condo_gourmet_area: 'Espaço gourmet',
    playground: 'Playground',
    sports_court: 'Quadra de esportes',
};

export const propertyPurposeLabels: Record<PropertyPurpose, string> = {
    rent: 'Para alugar',
    sale: 'À venda',
};

export const propertyStatusLabels: Record<PropertyStatus, string> = {
    available: 'Disponível',
    rented: 'Alugado',
    maintenance: 'Manutenção',
    inactive: 'Inativo',
    sold: 'Vendido',
};

export const propertyStatusBadgeClasses: Record<PropertyStatus, string> = {
    available: 'border-primary/25 bg-primary/10 text-primary',
    rented: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/60 dark:text-sky-300',
    maintenance:
        'border-attention-border bg-attention text-attention-foreground',
    inactive:
        'border-zinc-200 bg-zinc-100 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400',
    sold: 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950/60 dark:text-violet-300',
};

export const propertyStatusDotClasses: Record<PropertyStatus, string> = {
    available: 'bg-primary',
    rented: 'bg-sky-500',
    maintenance: 'bg-attention-strong',
    inactive: 'bg-zinc-400',
    sold: 'bg-violet-500',
};

type PropertyFeatures = Pick<
    Property,
    | 'bedrooms'
    | 'suites'
    | 'bathrooms'
    | 'parking_spaces'
    | 'built_area'
    | 'land_area'
    | 'land_front'
    | 'land_depth'
>;

function plural(count: number, singular: string, pluralForm: string): string {
    return `${count} ${count === 1 ? singular : pluralForm}`;
}

function formatArea(value: string): string {
    return `${new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 }).format(Number(value))} m²`;
}

/** Summarize the characteristics, e.g. "3 quartos (1 suíte) · 2 banheiros · 2 vagas · 120 m²". */
export function formatPropertyFeatures(property: PropertyFeatures): string {
    const parts: string[] = [];

    if (property.bedrooms) {
        const suites = property.suites
            ? ` (${plural(property.suites, 'suíte', 'suítes')})`
            : '';

        parts.push(
            `${plural(property.bedrooms, 'quarto', 'quartos')}${suites}`,
        );
    }

    if (property.bathrooms) {
        parts.push(plural(property.bathrooms, 'banheiro', 'banheiros'));
    }

    if (property.parking_spaces) {
        parts.push(plural(property.parking_spaces, 'vaga', 'vagas'));
    }

    if (property.built_area) {
        parts.push(`${formatArea(property.built_area)} construídos`);
    }

    if (property.land_area) {
        parts.push(`terreno de ${formatArea(property.land_area)}`);
    }

    if (property.land_front && property.land_depth) {
        const meters = (value: string) =>
            new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 }).format(
                Number(value),
            );

        parts.push(
            `${meters(property.land_front)} x ${meters(property.land_depth)} m`,
        );
    }

    return parts.join(' · ');
}
