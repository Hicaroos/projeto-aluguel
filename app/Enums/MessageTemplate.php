<?php

namespace App\Enums;

use App\Models\Account;

/**
 * The WhatsApp messages the team sends from their own phone, opened with the text already written. Each one
 * has a default text the account may rewrite, with {variables} filled from the payment, payout or adjustment.
 * A line whose variable comes out blank, such as the Pix key of an account without one, is left out.
 */
enum MessageTemplate: string
{
    case PaymentReminder = 'payment_reminder';
    case PaymentOverdue = 'payment_overdue';
    case PayoutNotice = 'payout_notice';
    case AdjustmentNotice = 'adjustment_notice';

    /**
     * The longest text the account may write.
     */
    public const int MAX_LENGTH = 1000;

    /**
     * Get the name shown to people.
     */
    public function label(): string
    {
        return match ($this) {
            self::PaymentReminder => 'Lembrete de vencimento',
            self::PaymentOverdue => 'Cobrança de atraso',
            self::PayoutNotice => 'Aviso de repasse',
            self::AdjustmentNotice => 'Aviso de reajuste',
        };
    }

    /**
     * Get the label of the button that opens the message.
     */
    public function actionLabel(): string
    {
        return match ($this) {
            self::PaymentReminder => 'Lembrar vencimento',
            self::PaymentOverdue => 'Cobrar atraso',
            self::PayoutNotice => 'Avisar repasse',
            self::AdjustmentNotice => 'Avisar reajuste',
        };
    }

    /**
     * Get when the message is offered, shown next to its text in the settings.
     */
    public function description(): string
    {
        return match ($this) {
            self::PaymentReminder => 'Para o inquilino, em cobranças em aberto que ainda não venceram.',
            self::PaymentOverdue => 'Para o inquilino, em cobranças vencidas.',
            self::PayoutNotice => 'Para o proprietário, em cada repasse do extrato.',
            self::AdjustmentNotice => 'Para o inquilino, no último reajuste do contrato.',
        };
    }

    /**
     * Determine whether the account sends this message: single owners receive their rent themselves, with no payouts.
     */
    public function isAvailableFor(Account $account): bool
    {
        return $this !== self::PayoutNotice || $account->isAgency();
    }

    /**
     * Get the text used while the account keeps it blank.
     */
    public function defaultText(): string
    {
        return match ($this) {
            self::PaymentReminder => <<<'TEXT'
                Olá, {inquilino}! Tudo bem?
                Passando para lembrar do vencimento de *{referencia}* do imóvel {imovel}.
                Valor: {valor}
                Vencimento: {vencimento}
                Chave Pix: {chave_pix}
                Qualquer dúvida, é só chamar.
                {imobiliaria}
                TEXT,
            self::PaymentOverdue => <<<'TEXT'
                Olá, {inquilino}! Tudo bem?
                Ainda não identificamos o pagamento de *{referencia}* do imóvel {imovel}, que venceu em {vencimento} ({dias_atraso} de atraso).
                Valor para pagar hoje, com multa e juros: {valor_atualizado}
                Chave Pix: {chave_pix}
                Se já pagou, por favor, envie o comprovante por aqui.
                {imobiliaria}
                TEXT,
            self::PayoutNotice => <<<'TEXT'
                Olá, {proprietario}! Tudo bem?
                O repasse de {valor} foi feito em {data}.
                Se quiser o extrato com os detalhes, é só pedir por aqui.
                {imobiliaria}
                TEXT,
            self::AdjustmentNotice => <<<'TEXT'
                Olá, {inquilino}! Tudo bem?
                Conforme o contrato, o aluguel do imóvel {imovel} foi reajustado em {percentual} ({indice}).
                A partir de {vigencia}, o valor passa de {valor_anterior} para {valor_novo}.
                Qualquer dúvida, é só chamar.
                {imobiliaria}
                TEXT,
        };
    }

    /**
     * Get the variables the message may use, with the label shown on each tag in the editor.
     *
     * @return array<string, string>
     */
    public function variables(Account $account): array
    {
        $agency = ['imobiliaria' => $account->isAgency() ? 'Nome da imobiliária' : 'Seu nome'];
        $pixKey = ['chave_pix' => $account->isAgency() ? 'Chave Pix da imobiliária' : 'Sua chave Pix'];

        return match ($this) {
            self::PaymentReminder => [
                'inquilino' => 'Nome do inquilino',
                'imovel' => 'Endereço do imóvel',
                'referencia' => 'Referência da cobrança',
                'valor' => 'Valor em aberto',
                'vencimento' => 'Vencimento',
                ...$pixKey,
                ...$agency,
            ],
            self::PaymentOverdue => [
                'inquilino' => 'Nome do inquilino',
                'imovel' => 'Endereço do imóvel',
                'referencia' => 'Referência da cobrança',
                'valor' => 'Valor sem encargos',
                'valor_atualizado' => 'Valor com multa e juros',
                'vencimento' => 'Vencimento',
                'dias_atraso' => 'Dias de atraso',
                ...$pixKey,
                ...$agency,
            ],
            self::PayoutNotice => [
                'proprietario' => 'Nome do proprietário',
                'valor' => 'Valor repassado',
                'data' => 'Data do repasse',
                ...$agency,
            ],
            self::AdjustmentNotice => [
                'inquilino' => 'Nome do inquilino',
                'imovel' => 'Endereço do imóvel',
                'percentual' => 'Percentual do reajuste',
                'indice' => 'Índice do reajuste',
                'valor_anterior' => 'Aluguel anterior',
                'valor_novo' => 'Aluguel reajustado',
                'vigencia' => 'Início do novo valor',
                ...$agency,
            ],
        };
    }

    /**
     * Get example values for the variables, used to preview the message in the settings.
     *
     * @return array<string, string>
     */
    public function sampleValues(Account $account): array
    {
        return collect([
            'inquilino' => 'Maria Souza',
            'proprietario' => 'João Lima',
            'imovel' => 'Rua das Flores, 120',
            'referencia' => 'aluguel de outubro/2026',
            'valor' => 'R$ 1.200,00',
            'valor_atualizado' => 'R$ 1.324,00',
            'vencimento' => '10/10/2026',
            'dias_atraso' => '5 dias',
            'chave_pix' => $account->paymentPixKey() ?? '',
            'data' => '15/10/2026',
            'percentual' => '4,50%',
            'indice' => AdjustmentIndex::Igpm->label(),
            'valor_anterior' => 'R$ 1.200,00',
            'valor_novo' => 'R$ 1.254,00',
            'vigencia' => '01/11/2026',
            'imobiliaria' => $account->name,
        ])->only(array_keys($this->variables($account)))->all();
    }
}
