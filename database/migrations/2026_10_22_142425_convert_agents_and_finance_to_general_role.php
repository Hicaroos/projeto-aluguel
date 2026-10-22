<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Turn the agents and finance members into general members, now the only role besides administrators.
     */
    public function up(): void
    {
        DB::table('users')->whereIn('role', ['agent', 'finance'])->update(['role' => 'general']);
    }

    /**
     * The former roles of each member are not kept, so there is nothing to bring back.
     */
    public function down(): void
    {
        //
    }
};
