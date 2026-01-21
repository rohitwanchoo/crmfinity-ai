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
        Schema::create('learned_transaction_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('description_hash', 64)->index(); // MD5 hash of normalized description
            $table->string('normalized_description', 500); // Normalized description pattern
            $table->string('original_description', 500); // Original description
            $table->string('transaction_type', 10); // credit or debit
            $table->decimal('amount', 15, 2)->nullable(); // Reference amount
            $table->string('source', 20)->default('openai'); // openai or manual
            $table->boolean('is_manual_override')->default(false); // Manual corrections take priority
            $table->integer('occurrence_count')->default(1); // How many times seen
            $table->integer('confidence_score')->default(100); // 0-100 confidence
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            // Unique constraint on hash to prevent duplicates
            $table->unique(['description_hash', 'transaction_type'], 'unique_pattern_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learned_transaction_patterns');
    }
};
