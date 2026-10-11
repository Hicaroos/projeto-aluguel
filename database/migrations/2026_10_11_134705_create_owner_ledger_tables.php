<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Each owner has a running account with the agency (conta-corrente): every rent received,
     * fee, expense or payout is an entry, positive when it is the owner's and negative when it is
     * taken from them, and the balance is what is left to pay out. Entries keep the rate and share
     * they were worked out with, so changing a rule later never rewrites the past. They go away
     * with what made them, e.g. when a receipt registered by mistake is removed.
     */
    public function up(): void
    {
        Schema::create('owner_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->index()->constrained();
            $table->foreignId('owner_id')->index()->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('paid_on');
            $table->string('payment_method')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'paid_on']);
        });

        Schema::create('owner_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained();
            $table->foreignId('owner_id')->constrained();
            $table->foreignId('property_id')->nullable()->index()->constrained();
            $table->foreignId('lease_id')->nullable()->index()->constrained();
            $table->foreignId('payment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('receipt_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->foreignId('expense_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->foreignId('owner_payout_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->decimal('base_amount', 12, 2)->nullable();
            $table->decimal('rate_percent', 5, 2)->nullable();
            $table->decimal('share_percent', 5, 2)->nullable();
            $table->boolean('is_guaranteed')->default(false);
            $table->date('occurred_on');
            $table->date('available_on');
            $table->timestamps();

            $table->index(['account_id', 'owner_id', 'occurred_on']);
            $table->index(['payment_id', 'owner_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('owner_ledger_entries');
        Schema::dropIfExists('owner_payouts');
    }
};
