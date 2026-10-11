<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The front and depth of the land, in meters, as buyers usually ask for them (e.g. 10 x 25 m).
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('land_front', 8, 2)->nullable()->after('land_area');
            $table->decimal('land_depth', 8, 2)->nullable()->after('land_front');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['land_front', 'land_depth']);
        });
    }
};
