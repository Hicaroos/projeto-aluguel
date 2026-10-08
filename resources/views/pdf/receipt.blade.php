<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Recibo — {{ $tenant->name }}</title>
    <style>
        @page {
            margin: 2cm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10.5pt;
            line-height: 1.55;
            color: #27272a;
        }

        .muted {
            color: #71717a;
        }

        .header {
            width: 100%;
            border-bottom: 1px solid #e4e4e7;
            padding-bottom: 18pt;
            margin-bottom: 22pt;
        }

        .header td {
            vertical-align: top;
        }

        .app-name {
            font-size: 9pt;
            margin: 0 0 8pt;
        }

        h1 {
            font-size: 18pt;
            margin: 0;
            color: #18181b;
        }

        .amount-box {
            border: 1px solid #e4e4e7;
            background: #fafafa;
            border-radius: 6pt;
            padding: 8pt 14pt;
            text-align: right;
        }

        .amount-box .label {
            font-size: 7.5pt;
            letter-spacing: 0.5pt;
            text-transform: uppercase;
        }

        .amount-box .value {
            font-size: 17pt;
            font-weight: bold;
            color: #18181b;
        }

        p {
            margin: 0 0 10pt;
            text-align: justify;
        }

        strong {
            color: #18181b;
        }

        .details {
            width: 100%;
            border: 1px solid #e4e4e7;
            border-radius: 6pt;
            border-collapse: separate;
            padding: 8pt;
            margin: 18pt 0 26pt;
        }

        .details td {
            width: 25%;
            padding: 4pt 6pt;
            vertical-align: top;
        }

        .details .label {
            font-size: 8.5pt;
            color: #71717a;
        }

        .details .value {
            font-weight: bold;
            color: #18181b;
        }

        .signature {
            margin-top: 60pt;
            text-align: center;
        }

        .signature .line {
            width: 60%;
            margin: 0 auto 6pt;
            border-top: 1px solid #a1a1aa;
        }

        .signature p {
            text-align: center;
            margin: 0;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <p class="app-name muted">{{ $appName }}</p>
                <h1>Recibo de aluguel</h1>
            </td>
            <td style="width: 38%;">
                <div class="amount-box">
                    <div class="label muted">Valor recebido</div>
                    <div class="value">R$ {{ number_format($total, 2, ',', '.') }}</div>
                </div>
            </td>
        </tr>
    </table>

    <p>
        Recebi de <strong>{{ $tenant->name }}</strong>{{ $tenantDocument ? ", inscrito(a) no CPF/CNPJ sob o nº {$tenantDocument}" : '' }},
        a importância de <strong>R$ {{ number_format($total, 2, ',', '.') }} ({{ $amountInWords }})</strong>,
        referente {{ $paymentDescription }} <strong>{{ $reference }}</strong> do imóvel situado à
        {{ $address }}{{ $hasLateCharges ? ', acrescido de multa e juros por atraso no pagamento' : '' }}.
    </p>

    <p>Pelo que dou plena e geral quitação do valor recebido.</p>

    <table class="details">
        <tr>
            <td>
                <div class="label">Referência</div>
                <div class="value">{{ $referenceLabel }}</div>
            </td>
            <td>
                <div class="label">Vencimento</div>
                <div class="value">{{ $receipt->payment->due_date->format('d/m/Y') }}</div>
            </td>
            <td>
                <div class="label">Data do pagamento</div>
                <div class="value">{{ $receipt->date->format('d/m/Y') }}</div>
            </td>
            <td>
                <div class="label">Forma de pagamento</div>
                <div class="value">{{ $receipt->payment_method?->label() ?? '—' }}</div>
            </td>
        </tr>
        @if ($hasLateCharges)
            <tr>
                <td>
                    <div class="label">Aluguel</div>
                    <div class="value">R$ {{ number_format((float) $receipt->amount, 2, ',', '.') }}</div>
                </td>
                <td>
                    <div class="label">Multa por atraso</div>
                    <div class="value">R$ {{ number_format((float) $receipt->late_fee_amount, 2, ',', '.') }}</div>
                </td>
                <td>
                    <div class="label">Juros</div>
                    <div class="value">R$ {{ number_format((float) $receipt->interest_amount, 2, ',', '.') }}</div>
                </td>
                <td>
                    <div class="label">Total recebido</div>
                    <div class="value">R$ {{ number_format($total, 2, ',', '.') }}</div>
                </td>
            </tr>
        @endif
        @if ($isPartial)
            <tr>
                <td colspan="4">
                    <div class="label">Valor total do aluguel</div>
                    <div class="value">R$ {{ number_format((float) $receipt->payment->amount, 2, ',', '.') }}</div>
                </td>
            </tr>
        @endif
        @if ($receipt->notes)
            <tr>
                <td colspan="4">
                    <div class="label">Observação</div>
                    <div class="value">{{ $receipt->notes }}</div>
                </td>
            </tr>
        @endif
    </table>

    <p>{{ $city }}, {{ $issuedOn }}.</p>

    <div class="signature">
        <div class="line"></div>
        <p><strong>{{ $owner->name }}</strong></p>
        @if ($ownerDocument)
            <p class="muted">CPF/CNPJ {{ $ownerDocument }}</p>
        @endif
        <p class="muted">Locador(a)</p>
    </div>
</body>
</html>
