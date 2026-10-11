<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Choices made case by case, over the payout rules: who keeps the late charges of a receipt,
     * whether an extra charge goes to the owner, and who bears an expense. A null follows the rules.
     */
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->decimal('late_charges_owner_percent', 5, 2)->nullable()->after('interest_amount');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('pass_to_owner')->nullable()->after('description');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('charged_to')->default('owner')->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('late_charges_owner_percent');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('pass_to_owner');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('charged_to');
        });
    }
};
