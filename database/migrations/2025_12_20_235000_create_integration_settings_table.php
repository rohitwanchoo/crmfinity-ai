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
        Schema::create('integration_settings', function (Blueprint $table) {
            $table->id();
            $table->string('integration')->unique(); // plaid, experian, persona, datamerch, ucc
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(false);
            $table->string('environment')->default('sandbox'); // sandbox, development, production
            $table->json('credentials')->nullable(); // Encrypted API keys, secrets, etc.
            $table->json('settings')->nullable(); // Additional configuration options
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable(); // success, failed
            $table->text('last_test_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_settings');
    }
};
