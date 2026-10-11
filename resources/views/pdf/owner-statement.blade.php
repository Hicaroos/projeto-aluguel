@php
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $signed = fn ($value) => ((float) $value < 0 ? '− ' : '').'R$ '.number_format(abs((float) $value), 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Extrato — {{ $owner->name }} — {{ $monthName }}</title>
    <style>
        @page {
            margin: 1.6cm 1.6cm 2cm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            line-height: 1.45;
            color: #27272a;
        }

        .muted {
            color: #71717a;
        }

        .header {
            width: 100%;
            border-bottom: 1px solid #e4e4e7;
            padding-bottom: 14pt;
            margin-bottom: 16pt;
        }

        .header td {
            vertical-align: top;
        }

        .agency {
            font-size: 8.5pt;
            margin: 0 0 6pt;
        }

        h1 {
            font-size: 16pt;
            margin: 0;
            color: #18181b;
        }

        .amount-box {
            border: 1px solid #e4e4e7;
            background: #fafafa;
            border-radius: 6pt;
            padding: 8pt 12pt;
            text-align: right;
        }

        .amount-box .label {
            font-size: 7pt;
            letter-spacing: 0.5pt;
            text-transform: uppercase;
        }

        .amount-box .value {
            font-size: 15pt;
            font-weight: bold;
            color: #18181b;
        }

        .owner {
            width: 100%;
            margin-bottom: 14pt;
        }

        .owner td {
            padding: 2pt 0;
            vertical-align: top;
        }

        .label {
            font-size: 7.5pt;
            color: #71717a;
        }

        .value {
            font-weight: bold;
            color: #18181b;
        }

        table.entries {
            width: 100%;
            border-collapse: collapse;
        }

        table.entries th {
            font-size: 7pt;
            letter-spacing: 0.4pt;
            text-transform: uppercase;
            color: #71717a;
            text-align: left;
            border-bottom: 1px solid #e4e4e7;
            padding: 5pt 4pt;
        }

        table.entries td {
            border-bottom: 1px solid #f4f4f5;
            padding: 5pt 4pt;
            vertical-align: top;
        }

        .right {
            text-align: right !important;
            white-space: nowrap;
        }

        .negative {
            color: #b91c1c;
        }

        .summary {
            width: 100%;
            margin-top: 14pt;
            border: 1px solid #e4e4e7;
            border-radius: 6pt;
            border-collapse: separate;
            padding: 6pt;
        }

        .summary td {
            width: 25%;
            padding: 4pt 6pt;
            vertical-align: top;
        }

        .note {
            margin-top: 14pt;
            font-size: 8pt;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <p class="agency muted">
                    {{ $agency->name }}{{ $agencyDocument ? " · CNPJ {$agencyDocument}" : '' }}{{ $agency->creci ? " · CRECI {$agency->creci}" : '' }}
                </p>
                <h1>Extrato do proprietário</h1>
                <p class="muted" style="margin: 2pt 0 0;">{{ $monthName }}</p>
            </td>
            <td style="width: 34%;">
                <div class="amount-box">
                    <div class="label muted">Saldo ao fim do mês</div>
                    <div class="value">{{ $signed($statement['closing_balance']) }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="owner">
        <tr>
            <td style="width: 50%;">
                <div class="label">Proprietário</div>
                <div class="value">{{ $owner->name }}</div>
                @if ($ownerDocument)
                    <div class="muted">CPF/CNPJ {{ $ownerDocument }}</div>
                @endif
            </td>
            <td>
                <div class="label">Chave Pix</div>
                <div class="value">{{ $owner->pix_key ?: '—' }}</div>
            </td>
        </tr>
    </table>

    <table class="entries">
        <thead>
            <tr>
                <th style="width: 11%;">Data</th>
                <th>Descrição</th>
                <th style="width: 24%;">Imóvel</th>
                <th class="right" style="width: 14%;">Valor</th>
                <th class="right" style="width: 14%;">Saldo</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td></td>
                <td class="muted">Saldo do mês anterior</td>
                <td></td>
                <td></td>
                <td class="right">{{ $signed($statement['opening_balance']) }}</td>
            </tr>
            @forelse ($statement['entries'] as $entry)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($entry['occurred_on'])->format('d/m/Y') }}</td>
                    <td>
                        {{ $entry['description'] }}
                        @if ($entry['share_percent'] !== null && (float) $entry['share_percent'] < 100)
                            <span class="muted">· sua parte: {{ rtrim(rtrim(number_format((float) $entry['share_percent'], 2, ',', ''), '0'), ',') }}%</span>
                        @endif
                        @if ($entry['is_pending'])
                            <div class="muted">A repassar a partir de {{ \Carbon\Carbon::parse($entry['available_on'])->format('d/m/Y') }}</div>
                        @endif
                    </td>
                    <td>
                        @if ($entry['property'])
                            {{ $entry['property']['street'] }}, {{ $entry['property']['number'] }}{{ $entry['property']['complement'] ? " - {$entry['property']['complement']}" : '' }}
                        @endif
                    </td>
                    <td class="right {{ (float) $entry['amount'] < 0 ? 'negative' : '' }}">{{ $signed($entry['amount']) }}</td>
                    <td class="right">{{ $signed($entry['balance']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">Nenhum lançamento neste mês.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary">
        <tr>
            <td>
                <div class="label">Saldo anterior</div>
                <div class="value">{{ $signed($statement['opening_balance']) }}</div>
            </td>
            <td>
                <div class="label">Créditos do mês</div>
                <div class="value">{{ $money($statement['credits']) }}</div>
            </td>
            <td>
                <div class="label">Débitos do mês</div>
                <div class="value">{{ $signed($statement['debits']) }}</div>
            </td>
            <td>
                <div class="label">Saldo ao fim do mês</div>
                <div class="value">{{ $signed($statement['closing_balance']) }}</div>
            </td>
        </tr>
    </table>

    @if (count($statement['deposits']) > 0)
        <p class="note">
            <strong>Cauções em guarda da imobiliária</strong> (informativo, fora do saldo):
            @foreach ($statement['deposits'] as $deposit)
                {{ $deposit['property'] }} ({{ $deposit['tenant'] }}): {{ $money($deposit['amount']) }}{{ $loop->last ? '.' : ';' }}
            @endforeach
        </p>
    @endif

    <p class="note muted">Emitido em {{ $issuedOn }}.</p>
</body>
</html>
