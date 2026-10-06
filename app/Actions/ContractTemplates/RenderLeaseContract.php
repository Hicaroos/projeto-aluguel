<?php

namespace App\Actions\ContractTemplates;

use App\Models\ContractTemplate;
use App\Models\Lease;
use DOMDocument;
use DOMDocumentFragment;
use DOMElement;
use DOMXPath;

class RenderLeaseContract
{
    public function __construct(private ResolveContractVariables $resolveContractVariables) {}

    /**
     * Fill the template variables with the lease details, returning the contract body as HTML.
     * Details that were not filled in become a blank line to complete by hand.
     */
    public function handle(ContractTemplate $template, Lease $lease): string
    {
        $values = $this->resolveContractVariables->handle($lease);

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="contract">'.$template->body.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        $placeholders = (new DOMXPath($document))->query('//span[@data-variable]');

        foreach ($placeholders === false ? [] : iterator_to_array($placeholders) as $placeholder) {
            if (! $placeholder instanceof DOMElement) {
                continue;
            }

            $value = $values[$placeholder->getAttribute('data-variable')] ?? null;

            $placeholder->parentNode?->replaceChild(
                $this->textWithLineBreaks($document, $value ?? ResolveContractVariables::BLANK),
                $placeholder,
            );
        }

        $container = $document->getElementById('contract');
        $html = '';

        foreach ($container === null ? [] : iterator_to_array($container->childNodes) as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    }

    /**
     * Build the escaped text of a value, turning its line breaks into <br> tags.
     */
    private function textWithLineBreaks(DOMDocument $document, string $value): DOMDocumentFragment
    {
        $fragment = $document->createDocumentFragment();

        foreach (explode("\n", $value) as $index => $line) {
            if ($index > 0) {
                $fragment->appendChild($document->createElement('br'));
            }

            $fragment->appendChild($document->createTextNode($line));
        }

        return $fragment;
    }
}
