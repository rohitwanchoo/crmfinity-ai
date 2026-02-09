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
            $table->decimal('ending_balance', 15, 2)->nullable()->after('amount')
                ->comment('Ending daily balance after this transaction');
            $table->decimal('beginning_balance', 15, 2)->nullable()->after('amount')
                ->comment('Balance before this transaction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analyzed_transactions', function (Blueprint $table) {
            $table->dropColumn(['ending_balance', 'beginning_balance']);
        });
    }
};
