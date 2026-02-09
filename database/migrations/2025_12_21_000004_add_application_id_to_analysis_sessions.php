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
            $table->unsignedBigInteger('application_id')->nullable()->after('user_id');
            $table->foreign('application_id')->references('id')->on('mca_applications')->onDelete('set null');
            $table->index('application_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analysis_sessions', function (Blueprint $table) {
            $table->dropForeign(['application_id']);
            $table->dropIndex(['application_id']);
            $table->dropColumn('application_id');
        });
    }
};
