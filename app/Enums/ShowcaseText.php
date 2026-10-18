<?php

namespace App\Enums;

use App\Models\Account;

/**
 * The texts of the showcase an account may rewrite. Each one has a default, used while the account
 * keeps it blank. Only the footer text shows for single owners; the rest belongs to the agency sections.
 */
enum ShowcaseText: string
{
    case About = 'about';
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

    /**
     * Get the text the showcase uses while the account keeps it blank.
     *
     * @param  string  $where  Where the account works, e.g. " em Ouricuri", or blank.
     */
    public function defaultText(Account $account, string $where): string
    {
        return match ($this) {
            self::About => $account->isAgency()
                ? "Venda, aluguel e administração de imóveis{$where}."
                : "Imóveis de {$account->name} para comprar e alugar{$where}.",
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
        };
    }

    /**
     * Get the longest text the account may write.
     */
    public function maxLength(): int
    {
        return match ($this) {
            self::About, self::WhyLead => 240,
            self::Benefit1Text, self::Benefit2Text, self::Benefit3Text, self::Benefit4Text => 160,
            self::Banner1Action, self::Banner2Action, self::Banner3Action => 30,
            default => 60,
        };
    }
}
