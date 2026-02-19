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
        // Plaid Items (bank connections)
        Schema::create('plaid_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('plaid_item_id')->unique();
            $table->text('access_token'); // encrypted
            $table->string('institution_id')->nullable();
            $table->string('institution_name');
            $table->string('status')->default('active'); // active, login_required, error, pending_expiration, revoked
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->text('transaction_cursor')->nullable();
            $table->timestamp('consent_expiration_time')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('has_pending_sync')->default(false);
            $table->boolean('auth_verified')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Plaid Accounts
        Schema::create('plaid_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plaid_item_id')->constrained()->onDelete('cascade');
            $table->string('plaid_account_id')->unique();
            $table->string('name');
            $table->string('official_name')->nullable();
            $table->string('type'); // depository, credit, loan, investment, other
            $table->string('subtype')->nullable(); // checking, savings, credit card, etc.
            $table->string('mask', 10)->nullable(); // last 4 digits
            $table->decimal('current_balance', 15, 2)->nullable();
            $table->decimal('available_balance', 15, 2)->nullable();
            $table->decimal('limit', 15, 2)->nullable();
            $table->string('iso_currency_code', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['plaid_item_id', 'type']);
        });

        // Plaid Transactions
        Schema::create('plaid_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plaid_account_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('plaid_transaction_id')->unique();
            $table->date('transaction_date');
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['credit', 'debit']);
            $table->string('category')->nullable();
            $table->string('subcategory')->nullable();
            $table->string('merchant_name')->nullable();
            $table->boolean('pending')->default(false);
            $table->string('iso_currency_code', 3)->default('USD');
            $table->string('payment_channel')->nullable(); // online, in store, other
            $table->json('location')->nullable();
            $table->timestamps();

            $table->index(['plaid_account_id', 'transaction_date']);
            $table->index(['type', 'category']);
            $table->index('merchant_name');
        });

        // Add source_type and plaid_transaction_id to analyzed_transactions if not exists
        if (Schema::hasTable('analyzed_transactions')) {
            Schema::table('analyzed_transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('analyzed_transactions', 'plaid_transaction_id')) {
                    $table->string('plaid_transaction_id')->nullable()->after('merchant_name');
                }
            });
        }

        // Add source_type to analysis_sessions if not exists
        if (Schema::hasTable('analysis_sessions')) {
            Schema::table('analysis_sessions', function (Blueprint $table) {
                if (! Schema::hasColumn('analysis_sessions', 'source_type')) {
                    $table->string('source_type')->default('pdf')->after('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove added columns
        if (Schema::hasTable('analyzed_transactions')) {
            Schema::table('analyzed_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('analyzed_transactions', 'plaid_transaction_id')) {
                    $table->dropColumn('plaid_transaction_id');
                }
            });
        }

        if (Schema::hasTable('analysis_sessions')) {
            Schema::table('analysis_sessions', function (Blueprint $table) {
                if (Schema::hasColumn('analysis_sessions', 'source_type')) {
                    $table->dropColumn('source_type');
                }
            });
        }

        Schema::dropIfExists('plaid_transactions');
        Schema::dropIfExists('plaid_accounts');
        Schema::dropIfExists('plaid_items');
    }
};
