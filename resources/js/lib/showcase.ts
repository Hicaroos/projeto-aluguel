import type {
    ShowcaseCard,
    ShowcaseEditableKey,
    ShowcasePurpose,
    ShowcaseSite,
} from '@/types';

/** What each text of the showcase is, shown while the account rewrites it. */
export const showcaseTextLabels: Record<ShowcaseEditableKey, string> = {
    headline: 'Título do topo',
    hero_lead: 'Texto do topo',
    rent_title: 'Título dos imóveis para alugar',
    sale_title: 'Título dos imóveis à venda',
    about: 'Texto do rodapé',
    why_title: 'Título de "Por que escolher"',
    why_lead: 'Introdução de "Por que escolher"',
    benefit_1_title: 'Vantagem 1',
    benefit_1_text: 'Explicação da vantagem 1',
    benefit_2_title: 'Vantagem 2',
    benefit_2_text: 'Explicação da vantagem 2',
    benefit_3_title: 'Vantagem 3',
    benefit_3_text: 'Explicação da vantagem 3',
    benefit_4_title: 'Vantagem 4',
    benefit_4_text: 'Explicação da vantagem 4',
    banner_1_title: 'Banner 1',
    banner_1_action: 'Chamada do banner 1',
    banner_2_title: 'Banner 2',
    banner_2_action: 'Chamada do banner 2',
    banner_3_title: 'Banner 3',
    banner_3_action: 'Chamada do banner 3',
    owner_title: 'Título da seção para proprietários',
    owner_lead: 'Texto da seção para proprietários',
    owner_point_1_title: 'Serviço 1 para proprietários',
    owner_point_1_text: 'Explicação do serviço 1',
    owner_point_2_title: 'Serviço 2 para proprietários',
    owner_point_2_text: 'Explicação do serviço 2',
    owner_point_3_title: 'Serviço 3 para proprietários',
    owner_point_3_text: 'Explicação do serviço 3',
    owner_form_title: 'Título do formulário de anúncio',
    owner_form_lead: 'Texto do formulário de anúncio',
};

/** Format a price as the showcase shows it, without cents: "R$ 320.000". */
export function formatShowcasePrice(value: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        maximumFractionDigits: 0,
    }).format(value);
}

/** Format an area, e.g. "160 m²" or "72,5 m²". */
export function formatArea(value: number): string {
    return `${value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })} m²`;
}

export function plural(count: number, one: string, many: string): string {
    return `${count} ${count === 1 ? one : many}`;
}

/** Sum up a property on one line: "3 quartos · 2 banheiros · 2 vagas · 160 m²". */
export function showcaseSpecs(property: ShowcaseCard): string {
    const area = property.built_area ?? property.land_area;

    return [
        property.bedrooms
            ? plural(property.bedrooms, 'quarto', 'quartos')
            : null,
        property.bathrooms
            ? plural(property.bathrooms, 'banheiro', 'banheiros')
            : null,
        property.parking_spaces
            ? plural(property.parking_spaces, 'vaga', 'vagas')
            : null,
        area ? formatArea(area) : null,
    ]
        .filter(Boolean)
        .join(' · ');
}

/** Say what the property is offered for: "Venda", "Aluguel" or both. */
export function purposeLabel(property: ShowcaseCard): string {
    if (property.sale_price !== null && property.rent !== null) {
        return 'Venda e aluguel';
    }

    return property.sale_price !== null ? 'Venda' : 'Aluguel';
}

/** Say how the property is on offer, for the badge on its photo: "Para alugar", "À venda" or both. */
export function offerLabel(property: ShowcaseCard): string {
    if (property.sale_price !== null && property.rent !== null) {
        return 'Venda e aluguel';
    }

    return property.sale_price !== null ? 'À venda' : 'Para alugar';
}

/** How the purpose is written in sentences: "à venda" or "para alugar". */
export const purposeWords: Record<ShowcasePurpose, string> = {
    sale: 'à venda',
    rent: 'para alugar',
};

/** How the purpose is written in the showcase addresses. */
export const purposeParams: Record<ShowcasePurpose, string> = {
    sale: 'comprar',
    rent: 'alugar',
};

/**
 * Get the WhatsApp link that opens a chat with the account, the message already written. Null when
 * the account has no WhatsApp number.
 */
export function whatsappUrl(
    site: ShowcaseSite,
    message: string,
): string | null {
    if (!site.whatsapp) {
        return null;
    }

    return `https://wa.me/55${site.whatsapp}?text=${encodeURIComponent(message)}`;
}

/** Write the message a visitor sends about a property. */
export function propertyMessage(property: ShowcaseCard): string {
    return `Olá! Vi no site o imóvel REF ${property.ref} (${property.title}, ${property.neighborhood}) e quero mais informações.`;
}

/** The highest prices visitors may filter by, for each purpose. */
export const priceSteps: Record<ShowcasePurpose, number[]> = {
    sale: [150000, 200000, 300000, 500000, 800000, 1000000],
    rent: [800, 1200, 1500, 2000, 3000, 5000],
};
