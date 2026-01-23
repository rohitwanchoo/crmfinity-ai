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
        Schema::create('transaction_categories', function (Blueprint $table) {
            $table->id();
            $table->string('description_pattern', 500)->index();
            $table->string('category', 100); // Category name (e.g., 'zelle', 'rent', 'utilities')
            $table->string('subcategory', 100)->nullable(); // Optional subcategory
            $table->string('transaction_type', 10); // 'credit', 'debit', or 'both'
            $table->integer('usage_count')->default(1);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index(['description_pattern', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_categories');
    }
};
