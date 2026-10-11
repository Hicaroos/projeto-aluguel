<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A property may have several owners (co-ownership), each with a share of what it earns. The
     * property's `owner_id` stays as the main owner, the contact who signs the contracts. Every
     * property starts with its main owner holding the whole of it.
     */
    public function up(): void
    {
        Schema::create('owner_property', function (Blueprint $table) {
            $table->foreignId('owner_id')->index()->constrained();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->decimal('share_percent', 5, 2);
            $table->timestamps();

            $table->primary(['property_id', 'owner_id']);
        });

        DB::table('properties')
            ->select('id', 'owner_id')
            ->orderBy('id')
            ->chunk(500, fn ($properties) => DB::table('owner_property')->insert(
                $properties->map(fn (object $property): array => [
                    'owner_id' => $property->owner_id,
                    'property_id' => $property->id,
                    'share_percent' => 100,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all(),
            ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('owner_property');
    }
};
