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
        Schema::create('revenue_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('description_pattern', 500);
            $table->enum('classification', ['true_revenue', 'adjustment'])->default('adjustment');
            $table->string('adjustment_reason')->nullable(); // e.g., 'transfer', 'loan', 'refund'
            $table->integer('usage_count')->default(1);
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();

            $table->index('description_pattern');
            $table->index('classification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenue_classifications');
    }
};
