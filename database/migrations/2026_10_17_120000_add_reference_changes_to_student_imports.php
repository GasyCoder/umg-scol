<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mentions, programs and domains an import creates in its establishment: planned at analysis, actual after commit. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_imports', fn (Blueprint $table) => $table->json('reference_changes')->nullable()->after('mapping'));
    }

    public function down(): void
    {
        Schema::table('student_imports', fn (Blueprint $table) => $table->dropColumn('reference_changes'));
    }
};
