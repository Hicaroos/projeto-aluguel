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
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->index()->constrained();
            $table->foreignId('payment_id')->index()->constrained();
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->string('payment_method')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
