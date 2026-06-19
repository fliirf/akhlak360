<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_assignments', function (Blueprint $table): void {
            $table->text('feedback')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_assignments', function (Blueprint $table): void {
            $table->dropColumn('feedback');
        });
    }
};
