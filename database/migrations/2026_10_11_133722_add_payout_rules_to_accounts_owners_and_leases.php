<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The rules that decide what owners are paid. The account holds the agency defaults; owners
     * and leases may set any of them otherwise, a null meaning "same as above".
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('admin_fee_percent', 5, 2)->default(10);
            $table->decimal('intermediation_fee_percent', 5, 2)->default(100);
            $table->decimal('renewal_fee_percent', 5, 2)->default(0);
            $table->boolean('admin_fee_with_intermediation')->default(false);
            $table->boolean('admin_fee_on_extra_charges')->default(false);
            $table->boolean('extra_charges_to_owner')->default(true);
            $table->decimal('late_charges_owner_percent', 5, 2)->default(100);
            $table->string('payout_mode')->default('received');
            $table->string('payout_schedule')->default('days_after');
            $table->unsignedTinyInteger('payout_days')->default(5);
            $table->unsignedTinyInteger('payout_day')->default(10);
        });

        foreach (['owners', 'leases'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('admin_fee_percent', 5, 2)->nullable();
                $table->decimal('intermediation_fee_percent', 5, 2)->nullable();
                $table->decimal('renewal_fee_percent', 5, 2)->nullable();
                $table->boolean('admin_fee_with_intermediation')->nullable();
                $table->boolean('admin_fee_on_extra_charges')->nullable();
                $table->boolean('extra_charges_to_owner')->nullable();
                $table->decimal('late_charges_owner_percent', 5, 2)->nullable();
                $table->string('payout_mode')->nullable();
                $table->string('payout_schedule')->nullable();
                $table->unsignedTinyInteger('payout_days')->nullable();
                $table->unsignedTinyInteger('payout_day')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['accounts', 'owners', 'leases'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn([
                    'admin_fee_percent',
                    'intermediation_fee_percent',
                    'renewal_fee_percent',
                    'admin_fee_with_intermediation',
                    'admin_fee_on_extra_charges',
                    'extra_charges_to_owner',
                    'late_charges_owner_percent',
                    'payout_mode',
                    'payout_schedule',
                    'payout_days',
                    'payout_day',
                ]);
            });
        }
    }
};
