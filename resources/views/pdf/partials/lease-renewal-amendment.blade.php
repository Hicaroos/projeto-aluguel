<h1>TERMO ADITIVO DE PRORROGAÇÃO DE CONTRATO DE LOCAÇÃO</h1>

<p><strong>LOCADOR:</strong> {{ $ownerQualification }}.</p>

<p><strong>LOCATÁRIO:</strong> {{ $tenantQualification }}.</p>

@if ($guarantorQualification)
    <p><strong>FIADOR:</strong> {{ $guarantorQualification }}.</p>
@endif

<p>
    As partes acima qualificadas, signatárias do contrato de locação do imóvel situado em {{ $propertyAddress }},
    com início em {{ $startDate }}, resolvem, de comum acordo, celebrar o presente Termo Aditivo, que se regerá
    pelas cláusulas seguintes.
</p>

<h2>CLÁUSULA PRIMEIRA — DA PRORROGAÇÃO</h2>

<p>
    O prazo da locação, que terminaria em {{ $previousEndDate }}, fica prorrogado por mais
    {{ $extensionMonths }} {{ $extensionMonths === 1 ? 'mês' : 'meses' }}, passando a terminar em
    {{ $newEndDate }}.
</p>

@if ($guaranteeClause)
    <h2>CLÁUSULA SEGUNDA — DA GARANTIA</h2>

    <p>{{ $guaranteeClause }}</p>
@endif

<h2>CLÁUSULA {{ $guaranteeClause ? 'TERCEIRA' : 'SEGUNDA' }} — DA RATIFICAÇÃO</h2>

<p>
    Ficam ratificadas todas as demais cláusulas e condições do contrato original que não conflitem com o
    presente Termo Aditivo, inclusive quanto ao valor do aluguel, à sua forma de reajuste e ao dia de vencimento.
</p>

<p>
    E, por estarem assim justas e acordadas, as partes assinam o presente Termo Aditivo em 2 (duas) vias de
    igual teor e forma, na presença das testemunhas abaixo.
</p>

<p>{{ $city }}, {{ $signedOn }}.</p>

<p>{!! nl2br(e($signatures)) !!}</p>
