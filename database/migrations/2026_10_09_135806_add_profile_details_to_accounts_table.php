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
            $table->string('legal_name')->nullable()->after('name');
            $table->string('email')->nullable()->after('phone');
            $table->string('zip_code')->nullable()->after('email');
            $table->string('street')->nullable()->after('zip_code');
            $table->string('number')->nullable()->after('street');
            $table->string('complement')->nullable()->after('number');
            $table->string('neighborhood')->nullable()->after('complement');
            $table->string('city')->nullable()->after('neighborhood');
            $table->string('state', 2)->nullable()->after('city');
            $table->string('logo_path')->nullable()->after('state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name',
                'email',
                'zip_code',
                'street',
                'number',
                'complement',
                'neighborhood',
                'city',
                'state',
                'logo_path',
            ]);
        });
    }
};
