<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MCA Applications
        Schema::create('mca_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_id')->unique();
            $table->integer('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Application Status
            $table->enum('status', [
                'draft', 'submitted', 'processing', 'under_review',
                'approved', 'declined', 'funded', 'closed',
            ])->default('draft');

            // Business Information
            $table->string('business_name');
            $table->string('dba_name')->nullable();
            $table->string('ein')->nullable();
            $table->string('business_type')->nullable();
            $table->string('industry')->nullable();
            $table->date('business_start_date')->nullable();
            $table->string('business_phone')->nullable();
            $table->string('business_email')->nullable();
            $table->string('website')->nullable();

            // Business Address
            $table->string('business_address')->nullable();
            $table->string('business_city')->nullable();
            $table->string('business_state', 2)->nullable();
            $table->string('business_zip', 10)->nullable();

            // Owner Information
            $table->string('owner_first_name');
            $table->string('owner_last_name');
            $table->string('owner_email');
            $table->string('owner_phone')->nullable();
            $table->string('owner_ssn_last4', 4)->nullable();
            $table->date('owner_dob')->nullable();
            $table->decimal('ownership_percentage', 5, 2)->default(100);

            // Owner Address
            $table->string('owner_address')->nullable();
            $table->string('owner_city')->nullable();
            $table->string('owner_state', 2)->nullable();
            $table->string('owner_zip', 10)->nullable();

            // Funding Request
            $table->decimal('requested_amount', 15, 2);
            $table->string('use_of_funds')->nullable();
            $table->decimal('monthly_revenue', 15, 2)->nullable();
            $table->integer('time_in_business_months')->nullable();

            // Risk Scoring
            $table->integer('overall_risk_score')->nullable();
            $table->string('risk_level')->nullable();
            $table->json('risk_details')->nullable();

            // Decision
            $table->string('decision')->nullable();
            $table->text('decision_notes')->nullable();
            $table->integer('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();

            // Offer Terms (if approved)
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->decimal('factor_rate', 5, 4)->nullable();
            $table->decimal('payback_amount', 15, 2)->nullable();
            $table->integer('term_months')->nullable();
            $table->decimal('daily_payment', 15, 2)->nullable();
            $table->decimal('holdback_percentage', 5, 4)->nullable();

            // Funding
            $table->timestamp('funded_at')->nullable();
            $table->string('funded_position')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('business_name');
            $table->index('owner_email');
        });

        // Verification Results
        Schema::create('verification_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('mca_applications')->onDelete('cascade');
            $table->string('verification_type'); // identity, credit, stacking, ucc, bank_analysis
            $table->string('provider'); // persona, experian, datamerch, ucc, internal
            $table->string('status')->default('pending');
            $table->integer('score')->nullable();
            $table->string('risk_level')->nullable();
            $table->json('raw_response')->nullable();
            $table->json('parsed_data')->nullable();
            $table->json('flags')->nullable();
            $table->string('external_id')->nullable(); // External reference ID
            $table->timestamps();

            $table->index(['application_id', 'verification_type']);
        });

        // Application Documents
        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('mca_applications')->onDelete('cascade');
            $table->string('document_type'); // bank_statement, id, voided_check, business_license, etc.
            $table->string('filename');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->integer('file_size');
            $table->string('storage_path');
            $table->boolean('is_processed')->default(false);
            $table->json('extracted_data')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'document_type']);
        });

        // Application Notes/Activity Log
        Schema::create('application_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('mca_applications')->onDelete('cascade');
            $table->integer('user_id')->nullable();
            $table->string('type')->default('note'); // note, status_change, verification, system
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'type']);
        });

        // Persona Inquiries
        Schema::create('persona_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('mca_applications')->onDelete('cascade');
            $table->string('inquiry_id')->unique();
            $table->string('status')->default('pending');
            $table->string('reference_id')->nullable();
            $table->json('inquiry_data')->nullable();
            $table->json('verification_results')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('application_id');
        });

        // Experian Reports
        Schema::create('credit_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('mca_applications')->onDelete('cascade');
            $table->string('report_type'); // consumer, business
            $table->string('provider')->default('experian');
            $table->integer('credit_score')->nullable();
            $table->json('score_factors')->nullable();
            $table->integer('open_accounts')->nullable();
            $table->integer('delinquent_accounts')->nullable();
            $table->decimal('total_debt', 15, 2)->nullable();
            $table->integer('bankruptcies')->nullable();
            $table->json('raw_report')->nullable();
            $table->json('analysis')->nullable();
            $table->timestamps();

            $table->index('application_id');
        });

        // DataMerch/Stacking Results
        Schema::create('stacking_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('mca_applications')->onDelete('cascade');
            $table->string('provider')->default('datamerch');
            $table->integer('active_mcas')->default(0);
            $table->integer('defaulted_mcas')->default(0);
            $table->decimal('total_exposure', 15, 2)->nullable();
            $table->integer('risk_score')->nullable();
            $table->string('risk_level')->nullable();
            $table->json('mca_details')->nullable();
            $table->string('recommendation')->nullable();
            $table->timestamps();

            $table->index('application_id');
        });

        // UCC Filings
        Schema::create('ucc_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('mca_applications')->onDelete('cascade');
            $table->integer('total_filings')->default(0);
            $table->integer('active_filings')->default(0);
            $table->integer('mca_related_filings')->default(0);
            $table->integer('blanket_liens')->default(0);
            $table->decimal('total_secured_amount', 15, 2)->nullable();
            $table->integer('risk_score')->nullable();
            $table->string('risk_level')->nullable();
            $table->json('filing_details')->nullable();
            $table->string('recommendation')->nullable();
            $table->timestamps();

            $table->index('application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ucc_reports');
        Schema::dropIfExists('stacking_reports');
        Schema::dropIfExists('credit_reports');
        Schema::dropIfExists('persona_inquiries');
        Schema::dropIfExists('application_notes');
        Schema::dropIfExists('application_documents');
        Schema::dropIfExists('verification_results');
        Schema::dropIfExists('mca_applications');
    }
};
