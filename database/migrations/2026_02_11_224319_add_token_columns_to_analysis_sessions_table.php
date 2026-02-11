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
        Schema::table('analysis_sessions', function (Blueprint $table) {
            $table->integer('input_tokens')->default(0)->after('api_cost');
            $table->integer('output_tokens')->default(0)->after('input_tokens');
            $table->integer('total_tokens')->default(0)->after('output_tokens');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analysis_sessions', function (Blueprint $table) {
            $table->dropColumn(['input_tokens', 'output_tokens', 'total_tokens']);
        });
    }
};
