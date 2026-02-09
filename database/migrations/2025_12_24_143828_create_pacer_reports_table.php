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
        Schema::create('pacer_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('mca_applications')->onDelete('cascade');
            $table->integer('total_cases')->default(0);
            $table->integer('bankruptcy_cases')->default(0);
            $table->integer('civil_cases')->default(0);
            $table->integer('judgments')->default(0);
            $table->integer('risk_score')->nullable();
            $table->string('risk_level')->nullable();
            $table->json('case_details')->nullable();
            $table->json('bankruptcy_details')->nullable();
            $table->json('judgment_details')->nullable();
            $table->string('recommendation')->nullable();
            $table->json('flags')->nullable();
            $table->string('search_name')->nullable();
            $table->string('search_type')->default('all');
            $table->timestamps();

            $table->index('application_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pacer_reports');
    }
};
