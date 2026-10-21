<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The landlord line of the standard lease template, before and after it repeats for each owner.
     */
    private const string SINGLE = '<p><strong>LOCADOR:</strong> <span data-variable="owner.qualification"></span>.</p>';

    private const string REPEATED = '<div data-repeat="owners">'.self::SINGLE.'</div>';

    /**
     * Run the migrations. Lease templates still holding the standard landlord line repeat it for each
     * owner, so co-owned properties keep naming every landlord now that the owner variables describe
     * the main owner only.
     */
    public function up(): void
    {
        $this->swap(self::SINGLE, self::REPEATED, fn (string $body): bool => ! str_contains($body, 'data-repeat'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->swap(self::REPEATED, self::SINGLE, fn (string $body): bool => true);
    }

    /**
     * @param  Closure(string): bool  $applies
     */
    private function swap(string $from, string $to, Closure $applies): void
    {
        DB::table('contract_templates')
            ->where('kind', 'lease')
            ->where('body', 'like', '%'.$from.'%')
            ->get(['id', 'body'])
            ->filter(fn (object $template): bool => $applies($template->body))
            ->each(fn (object $template) => DB::table('contract_templates')
                ->where('id', $template->id)
                ->update(['body' => str_replace($from, $to, $template->body)]));
    }
};
