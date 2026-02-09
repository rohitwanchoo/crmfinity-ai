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
        Schema::table('ground_truth_transactions', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });

        Schema::table('training_metrics', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });

        Schema::table('prediction_log', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ground_truth_transactions', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });

        Schema::table('training_metrics', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });

        Schema::table('prediction_log', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });
    }
};
