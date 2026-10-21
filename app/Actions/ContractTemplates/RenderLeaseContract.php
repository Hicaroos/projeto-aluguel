<?php

namespace App\Actions\ContractTemplates;

use App\Enums\ContractTemplateKind;
use App\Models\ContractTemplate;
use App\Models\Lease;

class RenderLeaseContract
{
    public function __construct(
        private ResolveContractVariables $resolveContractVariables,
        private RenderContractTemplate $renderContractTemplate,
    ) {}

    /**
     * Fill the template variables with the lease details, returning the contract body as HTML.
     * Details that were not filled in become a blank line to complete by hand.
     */
    public function handle(ContractTemplate $template, Lease $lease): string
    {
        return $this->render($template->body, $lease);
    }

    /**
     * Fill the variables of a template body, repeating the landlord blocks for each owner of the property.
     * Without a lease, each variable shows its label in brackets, e.g. "[Nome do locatário]", so a template
     * can be previewed before any lease exists.
     */
    public function render(string $body, ?Lease $lease): string
    {
        if ($lease === null) {
            return $this->renderContractTemplate->handle($body, $this->renderContractTemplate->placeholders(ContractTemplateKind::Lease));
        }

        return $this->renderContractTemplate->handle(
            $body,
            $this->resolveContractVariables->handle($lease),
            ['owners' => $this->resolveContractVariables->landlords($lease)],
        );
    }
}
