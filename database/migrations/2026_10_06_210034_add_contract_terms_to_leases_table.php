<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->string('purpose')->default('residential')->after('tenant_id');
            $table->string('adjustment_index')->default('igpm')->after('due_day');
            $table->decimal('late_fee_percent', 5, 2)->default(10)->after('adjustment_index');
            $table->decimal('monthly_interest_percent', 5, 2)->default(1)->after('late_fee_percent');
            $table->unsignedTinyInteger('termination_fee_months')->default(3)->after('monthly_interest_percent');
            $table->string('surety_insurer')->nullable()->after('deposit_amount');
            $table->string('surety_policy_number')->nullable()->after('surety_insurer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn([
                'purpose', 'adjustment_index', 'late_fee_percent', 'monthly_interest_percent',
                'termination_fee_months', 'surety_insurer', 'surety_policy_number',
            ]);
        });
    }
};
