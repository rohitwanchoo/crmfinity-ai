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
        Schema::table('application_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('analysis_session_id')->nullable()->after('is_processed');
            $table->decimal('true_revenue', 15, 2)->nullable()->after('analysis_session_id');
            $table->decimal('total_credits', 15, 2)->nullable()->after('true_revenue');
            $table->decimal('total_debits', 15, 2)->nullable()->after('total_credits');
            $table->integer('transaction_count')->nullable()->after('total_debits');
            $table->timestamp('analyzed_at')->nullable()->after('transaction_count');
            $table->string('fcs_report_path')->nullable()->after('analyzed_at');

            $table->foreign('analysis_session_id')->references('id')->on('analysis_sessions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropForeign(['analysis_session_id']);
            $table->dropColumn([
                'analysis_session_id',
                'true_revenue',
                'total_credits',
                'total_debits',
                'transaction_count',
                'analyzed_at',
                'fcs_report_path',
            ]);
        });
    }
};
