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
     * Owners are shared by the whole agency; this links them to the branches they deal with,
     * starting with the branches of the properties they already have.
     */
    public function up(): void
    {
        Schema::create('branch_owner', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->index()->constrained()->cascadeOnDelete();

            $table->primary(['branch_id', 'owner_id']);
        });

        DB::table('properties')
            ->whereNotNull('branch_id')
            ->select('branch_id', 'owner_id')
            ->distinct()
            ->get()
            ->each(fn (object $link) => DB::table('branch_owner')->insertOrIgnore([
                'branch_id' => $link->branch_id,
                'owner_id' => $link->owner_id,
            ]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_owner');
    }
};
