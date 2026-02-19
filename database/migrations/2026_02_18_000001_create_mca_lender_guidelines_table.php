<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mca_lender_guidelines', function (Blueprint $table) {
            $table->id();
            $table->string('lender_id', 100)->unique();
            $table->string('lender_name', 255);

            // Financial Criteria
            $table->integer('min_credit_score')->nullable();
            $table->integer('min_time_in_business')->nullable()->comment('In months');
            $table->decimal('min_loan_amount', 12, 2)->nullable();
            $table->decimal('max_loan_amount', 12, 2)->nullable();
            $table->integer('max_negative_days')->nullable();
            $table->integer('max_nsfs')->nullable()->comment('NSFs per month');
            $table->decimal('min_monthly_deposits', 12, 2)->nullable();
            $table->integer('max_positions')->nullable()->comment('Max open MCA positions allowed');
            $table->decimal('min_avg_daily_balance', 12, 2)->nullable();

            // Business Type Eligibility (YES / NO / MAYBE)
            $table->string('sole_proprietors', 20)->nullable();
            $table->string('home_based_business', 20)->nullable();
            $table->string('consolidation_deals', 20)->nullable();
            $table->string('non_profits', 20)->nullable();

            // Geographic Restrictions
            $table->text('restricted_states')->nullable()->comment('JSON array of state abbreviations');
            $table->text('excluded_industries')->nullable()->comment('JSON array of excluded industry names');

            // Funding Terms
            $table->string('funding_speed', 100)->nullable()->comment('e.g. SAME DAY, 24 HOURS, 48 HOURS');
            $table->string('factor_rate', 50)->nullable()->comment('e.g. 1.18 - 1.49');
            $table->string('max_term', 50)->nullable()->comment('e.g. 6 months, 12 months');
            $table->string('payment_frequency', 50)->nullable()->comment('DAILY or WEEKLY');
            $table->text('product_type')->nullable()->comment('MCA, Term Loan, Line of Credit, etc.');
            $table->boolean('bonus_available')->nullable();
            $table->string('bonus_details', 255)->nullable();

            // Special Circumstances
            $table->string('bankruptcy', 50)->nullable()->comment('YES / NO / DISMISSED ONLY / etc.');
            $table->string('tax_lien', 50)->nullable();
            $table->string('prior_default', 50)->nullable();
            $table->string('criminal_history', 50)->nullable();

            // Status
            $table->string('status', 20)->default('ACTIVE')->comment('ACTIVE or INACTIVE');
            $table->boolean('white_label')->default(false);
            $table->text('notes')->nullable();

            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();

            $table->index('lender_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mca_lender_guidelines');
    }
};
