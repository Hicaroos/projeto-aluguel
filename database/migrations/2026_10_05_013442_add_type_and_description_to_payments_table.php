<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Extra charges (e.g. a repair the tenant owes) have no reference month, so the
     * unique (lease_id, reference_month) index keeps applying only to monthly rent.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('type')->default('rent')->after('lease_id');
            $table->string('description')->nullable()->after('type');
            $table->date('reference_month')->nullable()->change();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('payment_id')->nullable()->after('property_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['type', 'description']);
            $table->date('reference_month')->nullable(false)->change();
        });
    }
};
