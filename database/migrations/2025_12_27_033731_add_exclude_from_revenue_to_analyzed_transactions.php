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
            $table->boolean('exclude_from_revenue')->default(false)->after('was_corrected');
            $table->string('exclusion_reason')->nullable()->after('exclude_from_revenue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analyzed_transactions', function (Blueprint $table) {
            $table->dropColumn(['exclude_from_revenue', 'exclusion_reason']);
        });
    }
};
