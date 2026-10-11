<?php

namespace App\Actions\ContractTemplates;

use App\Enums\ContractTemplateKind;
use App\Models\ContractTemplate;
use App\Models\Expense;

class RenderExpenseDocument
{
    public function __construct(
        private ResolveExpenseVariables $resolveExpenseVariables,
        private RenderContractTemplate $renderContractTemplate,
    ) {}

    /**
     * Fill the template variables with the expense details, returning the document body as HTML.
     */
    public function handle(ContractTemplate $template, Expense $expense): string
    {
        return $this->render($template->body, $expense);
    }

    /**
     * Fill the variables of a template body. Without an expense, each variable shows its label in brackets,
     * so a template can be previewed before any service expense exists.
     */
    public function render(string $body, ?Expense $expense): string
    {
        return $this->renderContractTemplate->handle(
            $body,
            $expense !== null
                ? $this->resolveExpenseVariables->handle($expense)
                : $this->renderContractTemplate->placeholders(ContractTemplateKind::ServiceReceipt),
        );
    }
}
