<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('sso_code_hash')->nullable()->after('hris_external_id');
            $table->timestamp('sso_code_generated_at')->nullable()->after('sso_code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['sso_code_hash', 'sso_code_generated_at']);
        });
    }
};
