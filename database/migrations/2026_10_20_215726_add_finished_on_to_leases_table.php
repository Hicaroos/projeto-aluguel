<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Leases finished before the date was kept are taken as finished on their
     * end date or, when they were terminated early, on the day they were last changed.
     */
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->date('finished_on')->nullable()->after('status');
        });

        DB::table('leases')
            ->where('status', '!=', 'active')
            ->orderBy('id')
            ->select(['id', 'end_date', 'updated_at'])
            ->chunkById(500, function ($leases): void {
                foreach ($leases as $lease) {
                    $endDate = Carbon::parse($lease->end_date);
                    $changedOn = $lease->updated_at !== null ? Carbon::parse($lease->updated_at)->startOfDay() : $endDate;

                    DB::table('leases')->where('id', $lease->id)->update([
                        'finished_on' => $endDate->min($changedOn)->toDateString(),
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn('finished_on');
        });
    }
};
