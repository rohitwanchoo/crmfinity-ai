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
        Schema::create('api_usage_logs', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->unsignedBigInteger('analysis_session_id')->nullable();
            $table->unsignedBigInteger('user_id');

            // API provider and model information
            $table->string('api_provider')->default('anthropic'); // 'anthropic' or 'openai'
            $table->string('model_used');

            // Token usage
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->integer('total_tokens')->default(0);

            // Cost breakdown (in USD)
            $table->decimal('input_cost', 10, 6)->default(0);
            $table->decimal('output_cost', 10, 6)->default(0);
            $table->decimal('total_cost', 10, 6)->default(0);

            // Additional metadata
            $table->string('extraction_method')->nullable(); // 'ai', 'deterministic', etc.
            $table->integer('response_time_ms')->nullable();
            $table->enum('status', ['success', 'failed', 'partial'])->default('success');
            $table->text('error_message')->nullable();

            // Context information
            $table->string('endpoint')->nullable(); // e.g., '/v1/messages'
            $table->json('metadata')->nullable(); // Additional custom data

            $table->timestamps();

            // Indexes for common queries
            $table->index('analysis_session_id');
            $table->index('user_id');
            $table->index(['user_id', 'created_at']);
            $table->index(['api_provider', 'created_at']);
            $table->index(['model_used', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_usage_logs');
    }
};
