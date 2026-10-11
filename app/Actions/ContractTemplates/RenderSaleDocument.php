<?php

namespace App\Actions\ContractTemplates;

use App\Enums\ContractTemplateKind;
use App\Models\ContractTemplate;
use App\Models\Sale;

class RenderSaleDocument
{
    public function __construct(
        private ResolveSaleVariables $resolveSaleVariables,
        private RenderContractTemplate $renderContractTemplate,
    ) {}

    /**
     * Fill the template variables with the sale details, returning the document body as HTML.
     */
    public function handle(ContractTemplate $template, Sale $sale): string
    {
        return $this->render($template->body, $sale, $template->kind);
    }

    /**
     * Fill the variables of a template body. Without a sale, each variable shows its label in brackets,
     * so a template can be previewed before any sale exists.
     */
    public function render(string $body, ?Sale $sale, ContractTemplateKind $kind): string
    {
        return $this->renderContractTemplate->handle(
            $body,
            $sale !== null
                ? $this->resolveSaleVariables->handle($sale)
                : $this->renderContractTemplate->placeholders($kind),
        );
    }
}
