<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fields of the existing student record to replace with the file value (choice made in the preview).
        Schema::table('student_import_rows', fn (Blueprint $table) => $table->json('identity_updates')->nullable()->after('corrections'));
    }

    public function down(): void
    {
        Schema::table('student_import_rows', fn (Blueprint $table) => $table->dropColumn('identity_updates'));
    }
};
