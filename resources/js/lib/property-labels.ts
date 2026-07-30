import type { PropertyStatus, PropertyType } from '@/types';

export const propertyTypeLabels: Record<PropertyType, string> = {
    house: 'Casa',
    apartment: 'Apartamento',
    commercial: 'Comercial',
    land: 'Terreno',
};

export const propertyStatusLabels: Record<PropertyStatus, string> = {
    available: 'Disponível',
    rented: 'Alugado',
    maintenance: 'Manutenção',
    inactive: 'Inativo',
};
