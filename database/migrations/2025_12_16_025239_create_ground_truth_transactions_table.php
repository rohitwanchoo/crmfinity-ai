<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ground_truth_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 100);
            $table->date('transaction_date');
            $table->text('description');
            $table->text('description_normalized')->nullable()->comment('Cleaned/normalized description');
            $table->decimal('amount', 15, 2);
            $table->enum('transaction_type', ['debit', 'credit', 'deposit', 'withdrawal']);
            $table->string('category')->nullable()->comment('Transaction category from MoneyThumb');
            $table->boolean('is_revenue')->default(false)->comment('Ground truth: Is this a revenue transaction?');
            $table->string('revenue_type', 100)->nullable()->comment('e.g., sales, service, recurring');
            $table->string('merchant_name')->nullable();
            $table->string('merchant_category', 100)->nullable();
            $table->decimal('confidence_score', 5, 4)->nullable()->comment('Ground truth confidence (if provided)');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('session_id')->on('training_sessions')->onDelete('cascade');
            $table->index('session_id');
            $table->index('transaction_date');
            $table->index('is_revenue');
            $table->index(['merchant_name', 'merchant_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ground_truth_transactions');
    }
};
