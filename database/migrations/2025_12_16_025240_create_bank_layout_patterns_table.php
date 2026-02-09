<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_layout_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');
            $table->string('layout_version', 50)->default('default');
            $table->string('date_format', 50)->nullable()->comment('e.g., MM/DD/YYYY, DD-MM-YYYY');
            $table->text('transaction_markers')->nullable()->comment('JSON: Keywords that identify transactions');
            $table->text('header_patterns')->nullable()->comment('JSON: Header identification patterns');
            $table->text('footer_patterns')->nullable()->comment('JSON: Footer identification patterns');
            $table->text('column_structure')->nullable()->comment('JSON: Column positions and labels');
            $table->text('extraction_rules')->nullable()->comment('JSON: Custom extraction rules for this bank');
            $table->integer('total_statements_seen')->default(1);
            $table->decimal('accuracy_rate', 5, 4)->nullable();
            $table->timestamps();

            $table->unique(['bank_name', 'layout_version'], 'unique_bank_layout');
            $table->index('bank_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_layout_patterns');
    }
};
