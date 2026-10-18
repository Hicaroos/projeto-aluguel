<?php

namespace App\Enums;

use App\Models\Account;

/**
 * The texts of the showcase an account may rewrite, right on the showcase home. Each one has a default,
 * used while the account keeps it blank. Single owners show only the hero, the carousel titles and the
 * footer; the rest belongs to the agency sections.
 */
enum ShowcaseText: string
{
    case HeroLead = 'hero_lead';
    case RentTitle = 'rent_title';
    case SaleTitle = 'sale_title';
    case About = 'about';
    case WhyTitle = 'why_title';
    case WhyLead = 'why_lead';
    case Benefit1Title = 'benefit_1_title';
    case Benefit1Text = 'benefit_1_text';
    case Benefit2Title = 'benefit_2_title';
    case Benefit2Text = 'benefit_2_text';
    case Benefit3Title = 'benefit_3_title';
    case Benefit3Text = 'benefit_3_text';
    case Benefit4Title = 'benefit_4_title';
    case Benefit4Text = 'benefit_4_text';
    case Banner1Title = 'banner_1_title';
    case Banner1Action = 'banner_1_action';
    case Banner2Title = 'banner_2_title';
    case Banner2Action = 'banner_2_action';
    case Banner3Title = 'banner_3_title';
    case Banner3Action = 'banner_3_action';
    case OwnerTitle = 'owner_title';
    case OwnerLead = 'owner_lead';
    case OwnerPoint1Title = 'owner_point_1_title';
    case OwnerPoint1Text = 'owner_point_1_text';
    case OwnerPoint2Title = 'owner_point_2_title';
    case OwnerPoint2Text = 'owner_point_2_text';
    case OwnerPoint3Title = 'owner_point_3_title';
    case OwnerPoint3Text = 'owner_point_3_text';
    case OwnerFormTitle = 'owner_form_title';
    case OwnerFormLead = 'owner_form_lead';

    /**
     * Get the text the showcase uses while the account keeps it blank.
     *
     * @param  string  $where  Where the account works, e.g. " em Ouricuri", or blank.
     */
    public function defaultText(Account $account, string $where): string
    {
        return match ($this) {
            self::HeroLead => $account->isAgency()
                ? 'Escolha o imóvel, peça uma visita pelo site e combine tudo direto com a equipe pelo WhatsApp.'
                : 'Escolha o imóvel e combine a visita direto com o proprietário.',
            self::RentTitle => 'Imóveis para alugar',
            self::SaleTitle => 'Imóveis à venda',
            self::About => $account->isAgency()
                ? "Venda, aluguel e administração de imóveis{$where}."
                : "Imóveis de {$account->name} para comprar e alugar{$where}.",
            self::WhyTitle => "Por que escolher {$account->name}?",
            self::WhyLead => 'Cuidamos do imóvel do começo ao fim: do anúncio ao contrato, da cobrança ao repasse do aluguel.',
            self::Benefit1Title => 'Repasse todo mês',
            self::Benefit1Text => 'O proprietário recebe o aluguel com o extrato de cada mês.',
            self::Benefit2Title => 'Inquilino com garantia',
            self::Benefit2Text => 'Contrato com fiador, caução ou seguro-fiança, como for melhor para o imóvel.',
            self::Benefit3Title => 'Cobrança acompanhada',
            self::Benefit3Text => 'A equipe cobra o aluguel e acompanha os atrasos para você não precisar.',
            self::Benefit4Title => 'Atendimento direto',
            self::Benefit4Text => 'Interessados e proprietários falam com a equipe pelo WhatsApp.',
            self::Banner1Title => 'Anuncie seu imóvel com a gente',
            self::Banner1Action => 'Fale com a equipe',
            self::Banner2Title => 'Quanto vale o seu imóvel?',
            self::Banner2Action => 'Peça uma avaliação',
            self::Banner3Title => 'Administração completa do aluguel',
            self::Banner3Action => 'Conheça o serviço',
            self::OwnerTitle => 'Tem um imóvel para alugar ou vender?',
            self::OwnerLead => "Deixe seus dados e a equipe de {$account->name} entra em contato para avaliar o imóvel e combinar o anúncio.",
            self::OwnerPoint1Title => 'Avaliação de preço',
            self::OwnerPoint1Text => 'Comparamos com imóveis parecidos no mesmo bairro.',
            self::OwnerPoint2Title => 'Divulgação no site',
            self::OwnerPoint2Text => 'Fotos e anúncio prontos para compartilhar no WhatsApp.',
            self::OwnerPoint3Title => 'Visitas acompanhadas',
            self::OwnerPoint3Text => 'A equipe mostra o imóvel e atende os interessados.',
            self::OwnerFormTitle => 'Anuncie com a gente',
            self::OwnerFormLead => 'Responda em um minuto. A equipe chama você no WhatsApp.',
        };
    }

    /**
     * Get the longest text the account may write.
     */
    public function maxLength(): int
    {
        return match ($this) {
            self::HeroLead, self::About, self::WhyLead, self::OwnerLead => 240,
            self::Benefit1Text, self::Benefit2Text, self::Benefit3Text, self::Benefit4Text,
            self::OwnerPoint1Text, self::OwnerPoint2Text, self::OwnerPoint3Text, self::OwnerFormLead => 160,
            self::WhyTitle, self::OwnerTitle => 80,
            self::Banner1Action, self::Banner2Action, self::Banner3Action => 30,
            default => 60,
        };
    }
}
