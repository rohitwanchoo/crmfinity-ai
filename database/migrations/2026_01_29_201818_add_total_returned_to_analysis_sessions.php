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
        Schema::table('analysis_sessions', function (Blueprint $table) {
            $table->decimal('total_returned', 15, 2)->default(0)->after('total_debits')
                ->comment('Total amount of returned/unpaid transactions');
            $table->integer('returned_count')->default(0)->after('total_returned')
                ->comment('Count of returned/unpaid transactions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analysis_sessions', function (Blueprint $table) {
            $table->dropColumn(['total_returned', 'returned_count']);
        });
    }
};
