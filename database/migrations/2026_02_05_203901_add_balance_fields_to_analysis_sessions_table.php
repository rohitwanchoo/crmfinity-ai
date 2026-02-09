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
            $table->decimal('beginning_balance', 15, 2)->nullable()->after('api_cost');
            $table->decimal('ending_balance', 15, 2)->nullable()->after('beginning_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analysis_sessions', function (Blueprint $table) {
            $table->dropColumn(['beginning_balance', 'ending_balance']);
        });
    }
};
