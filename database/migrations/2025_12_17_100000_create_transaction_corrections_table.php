<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_corrections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('description_pattern', 500)->comment('Transaction description or pattern');
            $table->string('original_type', 10)->comment('AI predicted type: credit or debit');
            $table->string('correct_type', 10)->comment('User corrected type: credit or debit');
            $table->decimal('amount', 15, 2)->nullable()->comment('Transaction amount for context');
            $table->integer('usage_count')->default(1)->comment('How many times this correction was applied');
            $table->timestamps();

            $table->index('description_pattern');
            $table->index('correct_type');
            $table->index(['description_pattern', 'correct_type']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_corrections');
    }
};
