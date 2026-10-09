<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tenants are shared by the whole agency; this links them to the branches they deal with,
     * starting with the branches of the properties they already rent.
     */
    public function up(): void
    {
        Schema::create('branch_tenant', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->index()->constrained()->cascadeOnDelete();

            $table->primary(['branch_id', 'tenant_id']);
        });

        DB::table('leases')
            ->join('properties', 'properties.id', '=', 'leases.property_id')
            ->whereNotNull('properties.branch_id')
            ->select('properties.branch_id', 'leases.tenant_id')
            ->distinct()
            ->get()
            ->each(fn (object $link) => DB::table('branch_tenant')->insertOrIgnore([
                'branch_id' => $link->branch_id,
                'tenant_id' => $link->tenant_id,
            ]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_tenant');
    }
};
