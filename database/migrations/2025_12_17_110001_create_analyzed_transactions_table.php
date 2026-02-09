<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analyzed_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_session_id')->constrained('analysis_sessions')->onDelete('cascade');
            $table->date('transaction_date');
            $table->text('description');
            $table->string('description_normalized', 500)->nullable()->comment('Normalized for pattern matching');
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['credit', 'debit']);
            $table->enum('original_type', ['credit', 'debit'])->comment('AI original prediction');
            $table->boolean('was_corrected')->default(false);
            $table->decimal('confidence', 5, 4)->default(0.8);
            $table->string('confidence_label', 10)->default('medium');
            $table->string('category', 100)->nullable();
            $table->string('merchant_name', 255)->nullable();
            $table->timestamps();

            $table->index('analysis_session_id');
            $table->index('transaction_date');
            $table->index('type');
            $table->index('description_normalized');
            $table->index('was_corrected');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyzed_transactions');
    }
};
