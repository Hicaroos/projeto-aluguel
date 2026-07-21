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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained();
            $table->foreignId('lease_id')->constrained();
            $table->date('reference_month');
            $table->date('due_date');
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['lease_id', 'reference_month']);
            $table->index(['status', 'due_date']);
            $table->index(['account_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
