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
        Schema::create('risk_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('conditions');
            $table->enum('logic', ['AND', 'OR'])->default('AND');
            $table->string('action');
            $table->text('action_value')->nullable();
            $table->enum('severity', ['low', 'medium', 'high'])->default('medium');
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('priority');
        });

        Schema::create('fraud_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->string('alert_type');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->text('description');
            $table->json('indicators')->nullable();
            $table->integer('fraud_score')->nullable();
            $table->enum('status', ['open', 'investigating', 'resolved', 'dismissed'])->default('open');
            $table->text('resolution_notes')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('application_id');
            $table->index('status');
            $table->index('severity');
        });

        Schema::create('underwriting_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->enum('decision', ['APPROVE', 'DECLINE', 'REVIEW', 'PENDING']);
            $table->enum('decision_type', ['automated', 'manual', 'rule', 'capacity']);
            $table->integer('risk_score');
            $table->string('risk_level');
            $table->text('decision_reason')->nullable();
            $table->string('reason_code')->nullable();
            $table->json('component_scores')->nullable();
            $table->json('flags')->nullable();
            $table->json('matched_rules')->nullable();
            $table->json('offer_terms')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->boolean('is_final')->default(false);
            $table->timestamps();

            $table->index('application_id');
            $table->index('decision');
            $table->index('decision_type');
            $table->index('created_at');
        });

        Schema::create('bank_analysis_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->string('bank_name')->nullable();
            $table->integer('transaction_count')->default(0);
            $table->decimal('total_credits', 15, 2)->default(0);
            $table->decimal('total_debits', 15, 2)->default(0);
            $table->decimal('average_monthly_revenue', 15, 2)->nullable();
            $table->decimal('average_daily_balance', 15, 2)->nullable();
            $table->integer('nsf_count')->default(0);
            $table->integer('negative_days')->default(0);
            $table->decimal('revenue_consistency', 5, 2)->nullable();
            $table->string('revenue_trend')->nullable();
            $table->decimal('mca_burden_ratio', 5, 2)->nullable();
            $table->integer('estimated_active_funders')->default(0);
            $table->integer('bank_score')->nullable();
            $table->string('bank_risk_level')->nullable();
            $table->json('monthly_breakdown')->nullable();
            $table->json('risk_indicators')->nullable();
            $table->json('full_analysis')->nullable();
            $table->timestamps();

            $table->index('application_id');
        });

        Schema::create('fraud_analysis_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->integer('fraud_score');
            $table->string('risk_level');
            $table->json('indicators')->nullable();
            $table->json('flags')->nullable();
            $table->integer('high_severity_flags')->default(0);
            $table->integer('medium_severity_flags')->default(0);
            $table->string('recommendation');
            $table->text('recommendation_reason')->nullable();
            $table->boolean('requires_review')->default(false);
            $table->json('review_focus')->nullable();
            $table->timestamps();

            $table->index('application_id');
            $table->index('fraud_score');
        });

        Schema::create('position_calculations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->decimal('monthly_revenue', 15, 2);
            $table->decimal('requested_amount', 15, 2);
            $table->integer('existing_position_count')->default(0);
            $table->decimal('current_burden_ratio', 5, 2)->nullable();
            $table->decimal('max_funding_amount', 15, 2)->nullable();
            $table->boolean('can_fund')->default(false);
            $table->decimal('recommended_amount', 15, 2)->nullable();
            $table->decimal('recommended_factor_rate', 4, 2)->nullable();
            $table->integer('recommended_term_months')->nullable();
            $table->decimal('recommended_daily_payment', 10, 2)->nullable();
            $table->string('stacking_risk_level')->nullable();
            $table->json('existing_positions')->nullable();
            $table->json('position_details')->nullable();
            $table->json('stacking_analysis')->nullable();
            $table->string('recommendation_decision')->nullable();
            $table->text('recommendation_reason')->nullable();
            $table->timestamps();

            $table->index('application_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('position_calculations');
        Schema::dropIfExists('fraud_analysis_results');
        Schema::dropIfExists('bank_analysis_results');
        Schema::dropIfExists('underwriting_decisions');
        Schema::dropIfExists('fraud_alerts');
        Schema::dropIfExists('risk_rules');
    }
};
