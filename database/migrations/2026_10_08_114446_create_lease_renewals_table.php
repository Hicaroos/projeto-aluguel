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
        Schema::create('lease_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->index()->constrained()->cascadeOnDelete();
            $table->date('previous_end_date');
            $table->date('new_end_date');
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lease_renewals');
    }
};
