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
        Schema::table('owner_payouts', function (Blueprint $table) {
            $table->string('receipt_name')->nullable()->after('notes');
            $table->string('receipt_path')->nullable()->after('receipt_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owner_payouts', function (Blueprint $table) {
            $table->dropColumn(['receipt_name', 'receipt_path']);
        });
    }
};
