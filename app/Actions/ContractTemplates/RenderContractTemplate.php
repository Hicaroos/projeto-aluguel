<?php

namespace App\Actions\ContractTemplates;

use App\Enums\ContractTemplateKind;
use App\Enums\ContractVariable;
use DOMDocument;
use DOMDocumentFragment;
use DOMElement;
use DOMNode;
use DOMXPath;

class RenderContractTemplate
{
    /**
     * Fill the variables of a template body with the given values, returning the document as HTML.
     * Details that were not filled in become a blank line to complete by hand. A block marked to
     * repeat, such as <div data-repeat="owners">, comes out once for each set of values given
     * under its name, each copy filled with that set; without them it comes out once.
     *
     * @param  array<string, string|null>  $values
     * @param  array<string, list<array<string, string|null>>>  $repeats
     */
    public function handle(string $body, array $values, array $repeats = []): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="contract">'.$body.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        $blocks = (new DOMXPath($document))->query('//div[@data-repeat]');

        foreach ($blocks === false ? [] : array_reverse(iterator_to_array($blocks)) as $block) {
            if (! $block instanceof DOMElement || $block->parentNode === null) {
                continue;
            }

            foreach ($repeats[$block->getAttribute('data-repeat')] ?? [[]] as $repeatValues) {
                $copy = $block->cloneNode(true);
                $block->parentNode->insertBefore($copy, $block);
                $this->fill($document, $copy, [...$values, ...$repeatValues]);
                $this->unwrap($copy);
            }

            $block->parentNode->removeChild($block);
        }

        $this->fill($document, $document, $values);

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
     * Swap the variable tags within the given node for their values.
     *
     * @param  array<string, string|null>  $values
     */
    private function fill(DOMDocument $document, DOMNode $within, array $values): void
    {
        $placeholders = (new DOMXPath($document))->query('.//span[@data-variable]', $within);

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
    }

    /**
     * Put the node's children in its place.
     */
    private function unwrap(DOMNode $node): void
    {
        while ($node->firstChild !== null) {
            $node->parentNode?->insertBefore($node->firstChild, $node);
        }

        $node->parentNode?->removeChild($node);
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
