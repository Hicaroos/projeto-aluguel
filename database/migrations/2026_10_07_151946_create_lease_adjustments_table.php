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
        Schema::create('lease_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->date('effective_on');
            $table->string('adjustment_index');
            $table->decimal('percent', 6, 2);
            $table->decimal('previous_amount', 10, 2);
            $table->decimal('new_amount', 10, 2);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['lease_id', 'effective_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lease_adjustments');
    }
};
