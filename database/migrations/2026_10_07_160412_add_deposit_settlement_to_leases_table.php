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
            $table->date('deposit_settled_on')->nullable()->after('deposit_amount');
            $table->decimal('deposit_refunded_amount', 10, 2)->nullable()->after('deposit_settled_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn(['deposit_settled_on', 'deposit_refunded_amount']);
        });
    }
};
