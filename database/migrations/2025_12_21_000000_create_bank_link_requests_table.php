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
        Schema::create('bank_link_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->string('token', 64)->unique(); // Unique token for the link
            $table->string('merchant_email');
            $table->string('merchant_name');
            $table->string('business_name');
            $table->enum('status', ['pending', 'sent', 'opened', 'completed', 'expired', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at');
            $table->string('plaid_item_id')->nullable(); // After successful connection
            $table->string('institution_name')->nullable();
            $table->json('accounts_connected')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->timestamps();

            $table->foreign('application_id')->references('id')->on('mca_applications')->onDelete('cascade');
            $table->index(['token', 'status']);
            $table->index(['application_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_link_requests');
    }
};
