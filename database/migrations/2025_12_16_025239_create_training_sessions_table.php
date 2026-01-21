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
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 100)->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('bank_name')->nullable();
            $table->string('bank_type', 100)->nullable()->comment('e.g., Chase, Wells Fargo, Bank of America');
            $table->date('statement_period_start')->nullable();
            $table->date('statement_period_end')->nullable();
            $table->string('statement_pdf_path', 500)->nullable();
            $table->string('ground_truth_pdf_path', 500)->nullable();
            $table->string('scorecard_pdf_path', 500)->nullable();
            $table->integer('total_transactions')->default(0);
            $table->integer('total_revenue_transactions')->default(0);
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('processing_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('session_id');
            $table->index('bank_type');
            $table->index('processing_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};
