<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prediction_log', function (Blueprint $table) {
            $table->id();
            $table->string('statement_id', 100)->nullable()->comment('Reference to analyzed statement');
            $table->text('transaction_description');
            $table->decimal('transaction_amount', 15, 2);
            $table->date('transaction_date')->nullable();
            $table->string('predicted_classification', 100);
            $table->string('predicted_revenue_type', 100)->nullable();
            $table->decimal('prediction_confidence', 5, 4);
            $table->text('patterns_used')->nullable()->comment('JSON: Patterns that led to this prediction');
            $table->string('actual_classification', 100)->nullable()->comment('If user corrects it');
            $table->boolean('is_correct')->nullable()->comment('NULL = not verified, 1 = correct, 0 = incorrect');
            $table->text('user_feedback')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('statement_id');
            $table->index('predicted_classification');
            $table->index('is_correct');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prediction_log');
    }
};
