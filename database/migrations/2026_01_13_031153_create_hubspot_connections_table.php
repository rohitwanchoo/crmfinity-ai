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
        Schema::create('hubspot_connections', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('hubspot_portal_id')->nullable();
            $table->string('hubspot_user_id')->nullable();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('token_expires_at');
            $table->json('scopes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'hubspot_portal_id']);
            $table->index('hubspot_portal_id');
        });

        Schema::create('hubspot_synced_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hubspot_connection_id')->constrained()->onDelete('cascade');
            $table->string('mca_offer_id');
            $table->string('hubspot_deal_id');
            $table->string('hubspot_contact_id')->nullable();
            $table->string('hubspot_company_id')->nullable();
            $table->enum('sync_status', ['synced', 'pending', 'failed'])->default('synced');
            $table->text('sync_error')->nullable();
            $table->timestamp('last_synced_at');
            $table->timestamps();

            $table->unique(['hubspot_connection_id', 'mca_offer_id']);
            $table->index('hubspot_deal_id');
        });

        Schema::create('hubspot_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('object_type');
            $table->string('object_id');
            $table->string('portal_id');
            $table->json('payload');
            $table->enum('status', ['received', 'processed', 'failed'])->default('received');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['portal_id', 'object_type', 'object_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hubspot_webhook_logs');
        Schema::dropIfExists('hubspot_synced_offers');
        Schema::dropIfExists('hubspot_connections');
    }
};
