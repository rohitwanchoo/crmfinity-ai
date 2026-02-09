<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'returned' to the type and original_type enum columns
        DB::statement("ALTER TABLE analyzed_transactions MODIFY COLUMN type ENUM('credit', 'debit', 'returned') NOT NULL");
        DB::statement("ALTER TABLE analyzed_transactions MODIFY COLUMN original_type ENUM('credit', 'debit', 'returned') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'returned' from the enum (note: this will fail if any rows have type='returned')
        DB::statement("ALTER TABLE analyzed_transactions MODIFY COLUMN type ENUM('credit', 'debit') NOT NULL");
        DB::statement("ALTER TABLE analyzed_transactions MODIFY COLUMN original_type ENUM('credit', 'debit') NOT NULL");
    }
};
