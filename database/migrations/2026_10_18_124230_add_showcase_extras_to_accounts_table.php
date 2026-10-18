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
            $table->boolean('showcase_shows_address')->default(false)->after('showcase_headline');
            $table->string('showcase_instagram')->nullable()->after('showcase_shows_address');
            $table->string('showcase_facebook')->nullable()->after('showcase_instagram');
            $table->string('showcase_youtube')->nullable()->after('showcase_facebook');
            $table->json('showcase_texts')->nullable()->after('showcase_youtube');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['showcase_shows_address', 'showcase_instagram', 'showcase_facebook', 'showcase_youtube', 'showcase_texts']);
        });
    }
};
