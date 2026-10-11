<?php

namespace App\Actions\ContractTemplates;

use App\Enums\ContractTemplateKind;
use App\Enums\ContractVariable;
use DOMDocument;
use DOMDocumentFragment;
use DOMElement;
use DOMXPath;

class RenderContractTemplate
{
    /**
     * Fill the variables of a template body with the given values, returning the document as HTML.
     * Details that were not filled in become a blank line to complete by hand.
     *
     * @param  array<string, string|null>  $values
     */
    public function handle(string $body, array $values): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="contract">'.$body.'</div>',
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
     * Get each variable of a kind of template as its label in brackets, e.g. "[Nome do comprador]",
     * so a template can be previewed before there is anything to fill it with.
     *
     * @return array<string, string>
     */
    public function placeholders(ContractTemplateKind $kind): array
    {
        return collect(ContractVariable::forKind($kind))
            ->mapWithKeys(fn (ContractVariable $variable): array => [$variable->value => "[{$variable->label()}]"])
            ->all();
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
