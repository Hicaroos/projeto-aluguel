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
        foreach (['tenants', 'owners'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('rg')->nullable()->after('cpf_cnpj');
                $table->string('nationality')->nullable()->after('rg');
                $table->string('marital_status')->nullable()->after('nationality');
                $table->string('profession')->nullable()->after('marital_status');
                $table->string('zip_code')->nullable()->after('phone');
                $table->string('street')->nullable()->after('zip_code');
                $table->string('number')->nullable()->after('street');
                $table->string('complement')->nullable()->after('number');
                $table->string('neighborhood')->nullable()->after('complement');
                $table->string('city')->nullable()->after('neighborhood');
                $table->string('state', 2)->nullable()->after('city');
            });
        }

        Schema::table('owners', function (Blueprint $table) {
            $table->string('pix_key')->nullable()->after('state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->dropColumn('pix_key');
        });

        foreach (['tenants', 'owners'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn([
                    'rg', 'nationality', 'marital_status', 'profession',
                    'zip_code', 'street', 'number', 'complement', 'neighborhood', 'city', 'state',
                ]);
            });
        }
    }
};
