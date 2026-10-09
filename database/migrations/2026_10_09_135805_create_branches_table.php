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
     * Every agency gets a "Matriz" branch holding the properties it already has.
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->index()->constrained();
            $table->string('name');
            $table->string('document', 14)->nullable();
            $table->string('creci')->nullable();
            $table->string('phone', 11)->nullable();
            $table->string('zip_code')->nullable();
            $table->string('street')->nullable();
            $table->string('number')->nullable();
            $table->string('complement')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->index()->after('account_id')->constrained();
        });

        DB::table('accounts')->where('type', 'agency')->orderBy('id')->each(function (object $account): void {
            $branchId = DB::table('branches')->insertGetId([
                'account_id' => $account->id,
                'name' => 'Matriz',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('properties')->where('account_id', $account->id)->update(['branch_id' => $branchId]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::dropIfExists('branches');
    }
};
