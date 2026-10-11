<?php

use App\Actions\ContractTemplates\BuildDocumentPdf;

/**
 * Get a text the way the PDF stores it with the embedded fonts, as UTF-16 code units.
 */
function pdfText(string $text): string
{
    return mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');
}

test('every page of a document is numbered with the page count', function () {
    $body = str_repeat('<p>Cláusula com texto suficiente para ocupar várias linhas do contrato, repetida até encher mais de uma página.</p>', 60);

    $pdf = app(BuildDocumentPdf::class)->handle('Contrato', $body);
    $pageCount = $pdf->getDomPDF()->getCanvas()->get_page_count();
    $output = $pdf->output(['compress' => 0]);

    expect($pageCount)->toBeGreaterThan(1);

    foreach (range(1, $pageCount) as $pageNumber) {
        expect($output)->toContain(pdfText("Página {$pageNumber} de {$pageCount}"));
    }

    expect($output)->not->toContain(pdfText('de 0'));
});

test('a one page document is numbered as such', function () {
    $output = app(BuildDocumentPdf::class)->handle('Recibo', '<p>Recebi a importância de R$ 100,00.</p>')->output(['compress' => 0]);

    expect($output)->toContain(pdfText('Página 1 de 1'));
});
