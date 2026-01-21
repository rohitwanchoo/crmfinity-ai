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
        Schema::create('mca_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('description_pattern', 500);
            $table->string('lender_id', 100); // e.g., 'kapitus', 'ondeck', 'custom_abc'
            $table->string('lender_name', 255); // e.g., 'Kapitus', 'OnDeck Capital'
            $table->boolean('is_mca')->default(true); // true = MCA, false = not MCA (learned exclusion)
            $table->integer('usage_count')->default(1);
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();

            $table->index('description_pattern');
            $table->index('lender_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mca_patterns');
    }
};
