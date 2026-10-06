<?php

namespace App\Actions\ContractTemplates;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class SanitizeContractTemplate
{
    /**
     * Largest template body accepted, in characters.
     */
    public const int MAX_LENGTH = 200_000;

    /**
     * Keep only the formatting the contract editor produces and the variable tags,
     * dropping scripts, styles, links and any other markup.
     */
    public function handle(string $html): string
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('h1')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('ul')
            ->allowElement('ol', ['start'])
            ->allowElement('li')
            ->allowElement('br')
            ->allowElement('hr')
            ->allowElement('blockquote')
            ->allowElement('span', ['data-variable'])
            ->withMaxInputLength(self::MAX_LENGTH);

        return trim((new HtmlSanitizer($config))->sanitize($html));
    }
}
