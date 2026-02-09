<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('analysis_sessions', 'analysis_type')) {
                $table->string('analysis_type')->nullable()->default('claude')->after('status');
            }
            if (!Schema::hasColumn('analysis_sessions', 'model_used')) {
                $table->string('model_used')->nullable()->after('analysis_type');
            }
            if (!Schema::hasColumn('analysis_sessions', 'api_cost')) {
                $table->decimal('api_cost', 10, 4)->nullable()->default(0)->after('model_used');
            }
        });
    }

    public function down(): void
    {
        Schema::table('analysis_sessions', function (Blueprint $table) {
            $table->dropColumn(['analysis_type', 'model_used', 'api_cost']);
        });
    }
};
