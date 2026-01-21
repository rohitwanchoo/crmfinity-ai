<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 50)->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('filename')->comment('Original uploaded filename');
            $table->integer('pages')->default(0);
            $table->integer('total_transactions')->default(0);
            $table->decimal('total_credits', 15, 2)->default(0);
            $table->decimal('total_debits', 15, 2)->default(0);
            $table->decimal('net_flow', 15, 2)->default(0);
            $table->integer('high_confidence_count')->default(0);
            $table->integer('medium_confidence_count')->default(0);
            $table->integer('low_confidence_count')->default(0);
            $table->string('status', 20)->default('completed');
            $table->timestamps();

            $table->index('user_id');
            $table->index('created_at');
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_sessions');
    }
};
