<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learned_patterns', function (Blueprint $table) {
            $table->id();
            $table->enum('pattern_type', ['merchant', 'description_keyword', 'amount_range', 'category', 'bank_format']);
            $table->string('pattern_key', 500)->comment('The pattern identifier (e.g., merchant name, keyword)');
            $table->text('pattern_value')->nullable()->comment('Additional pattern data (JSON)');
            $table->string('classification', 100)->comment('What this pattern indicates (e.g., revenue, expense)');
            $table->string('revenue_type', 100)->nullable();
            $table->decimal('confidence_score', 5, 4)->default(1.0000)->comment('Pattern confidence (0-1)');
            $table->integer('occurrence_count')->default(1)->comment('Number of times this pattern appeared');
            $table->decimal('accuracy_rate', 5, 4)->nullable()->comment('Historical accuracy of this pattern');
            $table->string('bank_specific')->nullable()->comment('NULL = universal, or specific bank name');
            $table->timestamps();

            $table->unique(['pattern_type', 'pattern_key', 'bank_specific'], 'unique_pattern');
            $table->index('pattern_type');
            $table->index('classification');
            $table->index('confidence_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learned_patterns');
    }
};
