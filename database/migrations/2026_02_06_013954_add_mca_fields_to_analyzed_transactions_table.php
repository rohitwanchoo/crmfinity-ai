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
        Schema::table('analyzed_transactions', function (Blueprint $table) {
            $table->boolean('is_mca_payment')->default(false)->after('category');
            $table->string('mca_lender_id')->nullable()->after('is_mca_payment');
            $table->string('mca_lender_name')->nullable()->after('mca_lender_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analyzed_transactions', function (Blueprint $table) {
            $table->dropColumn(['is_mca_payment', 'mca_lender_id', 'mca_lender_name']);
        });
    }
};
