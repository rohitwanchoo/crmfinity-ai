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
        Schema::table('revenue_classifications', function (Blueprint $table) {
            $table->boolean('is_mca_funding')->default(false)->after('adjustment_reason');
            $table->string('mca_lender_id')->nullable()->after('is_mca_funding');
            $table->string('mca_lender_name')->nullable()->after('mca_lender_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('revenue_classifications', function (Blueprint $table) {
            $table->dropColumn(['is_mca_funding', 'mca_lender_id', 'mca_lender_name']);
        });
    }
};
