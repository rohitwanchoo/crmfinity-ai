<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date')->unique();
            $table->integer('total_training_sessions')->default(0);
            $table->integer('total_transactions_learned')->default(0);
            $table->integer('total_patterns_identified')->default(0);
            $table->integer('total_merchants_profiled')->default(0);
            $table->integer('unique_banks_trained')->default(0);
            $table->decimal('average_accuracy_rate', 5, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_metrics');
    }
};
