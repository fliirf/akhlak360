<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('assessment_responses')
            ->select('assessment_assignment_id', 'core_value', 'indicator')
            ->groupBy('assessment_assignment_id', 'core_value', 'indicator')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function (object $duplicate): void {
                $ids = DB::table('assessment_responses')
                    ->where('assessment_assignment_id', $duplicate->assessment_assignment_id)
                    ->where('core_value', $duplicate->core_value)
                    ->where('indicator', $duplicate->indicator)
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->pluck('id');

                DB::table('assessment_responses')
                    ->whereIn('id', $ids->slice(1)->all())
                    ->delete();
            });

        Schema::table('assessment_responses', function (Blueprint $table): void {
            $table->unique(
                ['assessment_assignment_id', 'core_value', 'indicator'],
                'assessment_response_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('assessment_responses', function (Blueprint $table): void {
            $table->dropUnique('assessment_response_unique');
        });
    }
};
