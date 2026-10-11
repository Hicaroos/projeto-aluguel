<?php

namespace App\Actions\ContractTemplates;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocumentPdf;
use Dompdf\Canvas;
use Dompdf\FontMetrics;

class BuildDocumentPdf
{
    /**
     * Font size of the page number, in points.
     */
    private const float PAGE_NUMBER_SIZE = 8.5;

    /**
     * Distance from the bottom of the page to the top of the page number, in points.
     */
    private const float PAGE_NUMBER_BOTTOM = 45;

    /**
     * Lay a contract, receipt or amendment out on A4 pages, numbered at the bottom as "Página 1 de 3".
     *
     * The page count is only known once the document is laid out, so the numbers are written on the
     * finished pages: the CSS page counter that would do it in a browser always counts zero pages here.
     */
    public function handle(string $title, string $body): DocumentPdf
    {
        $pdf = Pdf::loadView('pdf.lease-contract', ['title' => $title, 'body' => $body])->setPaper('a4');
        $pdf->render();

        $pdf->getDomPDF()->getCanvas()->page_script(function (int $pageNumber, int $pageCount, Canvas $canvas, FontMetrics $fontMetrics): void {
            $font = $fontMetrics->getFont('DejaVu Serif');

            if ($font === null) {
                return;
            }

            $text = "Página {$pageNumber} de {$pageCount}";
            $width = $fontMetrics->getTextWidth($text, $font, self::PAGE_NUMBER_SIZE);

            $canvas->text(
                ($canvas->get_width() - $width) / 2,
                $canvas->get_height() - self::PAGE_NUMBER_BOTTOM,
                $text,
                $font,
                self::PAGE_NUMBER_SIZE,
                [0.4, 0.4, 0.4],
            );
        });

        return $pdf;
    }
}
