<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('merchant_name')->unique();
            $table->text('merchant_name_variations')->nullable()->comment('JSON array of name variations');
            $table->string('primary_category', 100)->nullable();
            $table->boolean('is_revenue_source')->default(false);
            $table->string('revenue_type', 100)->nullable();
            $table->decimal('typical_amount_min', 15, 2)->nullable();
            $table->decimal('typical_amount_max', 15, 2)->nullable();
            $table->string('typical_frequency', 50)->nullable()->comment('e.g., daily, weekly, monthly');
            $table->integer('total_occurrences')->default(1);
            $table->integer('revenue_occurrences')->default(0);
            $table->decimal('confidence_score', 5, 4)->default(1.0000);
            $table->timestamp('last_seen')->nullable();
            $table->timestamps();

            $table->index('is_revenue_source');
            $table->index('primary_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_profiles');
    }
};
