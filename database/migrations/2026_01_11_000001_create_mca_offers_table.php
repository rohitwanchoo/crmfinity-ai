<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mca_offers', function (Blueprint $table) {
            $table->id();
            $table->string('offer_id')->unique();
            $table->string('session_uuid'); // Links to analysis session
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            // Revenue inputs
            $table->decimal('true_revenue_monthly', 15, 2);
            $table->boolean('revenue_override')->default(false);
            $table->decimal('override_revenue', 15, 2)->nullable();

            // Existing MCA payment
            $table->decimal('existing_mca_payment', 15, 2)->default(0);

            // Withhold settings
            $table->decimal('withhold_percent', 5, 2)->default(20.00);
            $table->decimal('cap_amount', 15, 2); // true_revenue * withhold_pct / 100
            $table->decimal('new_payment_available', 15, 2); // cap_amount - existing_mca_payment

            // Offer terms
            $table->decimal('factor_rate', 5, 4);
            $table->integer('term_months');
            $table->decimal('advance_amount', 15, 2);

            // Calculated values
            $table->decimal('total_payback', 15, 2); // advance_amount * factor_rate
            $table->decimal('monthly_payment', 15, 2);
            $table->decimal('max_funded_amount', 15, 2); // Based on 20% constraint

            // Metadata
            $table->string('offer_name')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('session_uuid');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mca_offers');
    }
};
