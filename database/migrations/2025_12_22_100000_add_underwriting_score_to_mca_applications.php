<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mca_applications', function (Blueprint $table) {
            $table->integer('underwriting_score')->nullable()->after('overall_risk_score');
            $table->string('underwriting_decision', 50)->nullable()->after('underwriting_score');
            $table->json('underwriting_details')->nullable()->after('underwriting_decision');
            $table->timestamp('underwriting_calculated_at')->nullable()->after('underwriting_details');
        });
    }

    public function down(): void
    {
        Schema::table('mca_applications', function (Blueprint $table) {
            $table->dropColumn(['underwriting_score', 'underwriting_decision', 'underwriting_details', 'underwriting_calculated_at']);
        });
    }
};
