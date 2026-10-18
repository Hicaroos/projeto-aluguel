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
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('showcase_enabled')->default(false)->after('logo_path');
            $table->string('showcase_slug', 40)->nullable()->unique()->after('showcase_enabled');
            $table->string('showcase_whatsapp', 11)->nullable()->after('showcase_slug');
            $table->string('showcase_color', 20)->nullable()->after('showcase_whatsapp');
            $table->string('showcase_headline', 120)->nullable()->after('showcase_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique(['showcase_slug']);
            $table->dropColumn(['showcase_enabled', 'showcase_slug', 'showcase_whatsapp', 'showcase_color', 'showcase_headline']);
        });
    }
};
