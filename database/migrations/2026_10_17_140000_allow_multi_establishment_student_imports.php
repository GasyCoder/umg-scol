<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An import made by a global actor may cover several establishments: no establishment on the
 * import, each row names its own (Institution Code / École - Institut columns).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_imports', fn (Blueprint $table) => $table->unsignedBigInteger('establishment_id')->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('student_imports', fn (Blueprint $table) => $table->unsignedBigInteger('establishment_id')->nullable(false)->change());
    }
};
