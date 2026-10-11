<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Properties may be for rent, for sale or both, and get the characteristics buyers ask about.
     * The rent becomes optional, since properties only for sale have none.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->boolean('for_rent')->default(true)->after('type');
            $table->boolean('for_sale')->default(false)->after('for_rent');
            $table->decimal('rent_amount', 10, 2)->nullable()->change();
            $table->decimal('sale_price', 12, 2)->nullable()->after('rent_amount');
            $table->unsignedTinyInteger('bedrooms')->nullable()->after('sale_price');
            $table->unsignedTinyInteger('suites')->nullable()->after('bedrooms');
            $table->unsignedTinyInteger('bathrooms')->nullable()->after('suites');
            $table->unsignedTinyInteger('parking_spaces')->nullable()->after('bathrooms');
            $table->decimal('built_area', 10, 2)->nullable()->after('parking_spaces');
            $table->decimal('land_area', 10, 2)->nullable()->after('built_area');
            $table->text('description')->nullable()->after('land_area');

            $table->index(['account_id', 'for_sale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'for_sale']);
            $table->dropColumn([
                'for_rent',
                'for_sale',
                'sale_price',
                'bedrooms',
                'suites',
                'bathrooms',
                'parking_spaces',
                'built_area',
                'land_area',
                'description',
            ]);
            $table->decimal('rent_amount', 10, 2)->nullable(false)->change();
        });
    }
};
