<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A closed sale keeps who bought, who sold and for how much, plus the commission agencies earn,
     * at the rate the agency usually charges unless agreed otherwise.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->index()->constrained();
            $table->foreignId('property_id')->index()->constrained();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('seller_owner_id')->nullable()->constrained('owners')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('buyer_name');
            $table->string('buyer_document', 14)->nullable();
            $table->string('buyer_phone', 11)->nullable();
            $table->string('buyer_email')->nullable();
            $table->string('buyer_rg')->nullable();
            $table->string('buyer_nationality')->nullable();
            $table->string('buyer_marital_status')->nullable();
            $table->string('buyer_profession')->nullable();
            $table->string('buyer_zip_code')->nullable();
            $table->string('buyer_street')->nullable();
            $table->string('buyer_number')->nullable();
            $table->string('buyer_complement')->nullable();
            $table->string('buyer_neighborhood')->nullable();
            $table->string('buyer_city')->nullable();
            $table->string('buyer_state', 2)->nullable();
            $table->decimal('price', 12, 2);
            $table->date('sold_on');
            $table->string('payment_plan')->nullable();
            $table->decimal('down_payment', 12, 2)->nullable();
            $table->date('deed_deadline')->nullable();
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->decimal('commission_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('property_was_for_rent')->default(false);
            $table->string('rental_outcome')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'sold_on']);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('sale_commission_percent', 5, 2)->default(6)->after('logo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('sale_commission_percent');
        });

        Schema::dropIfExists('sales');
    }
};
