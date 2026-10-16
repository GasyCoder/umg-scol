<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Main causes of rejected/warning rows of an analysis, with the (non-personal) file values involved. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_imports', fn (Blueprint $table) => $table->json('issue_summary')->nullable()->after('reference_changes'));
    }

    public function down(): void
    {
        Schema::table('student_imports', fn (Blueprint $table) => $table->dropColumn('issue_summary'));
    }
};
