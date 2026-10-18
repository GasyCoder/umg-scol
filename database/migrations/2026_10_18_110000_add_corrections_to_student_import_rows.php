<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Values corrected in the preview, by field; the file as uploaded (raw_data) is kept unchanged.
        Schema::table('student_import_rows', fn (Blueprint $table) => $table->json('corrections')->nullable()->after('raw_data'));
    }

    public function down(): void
    {
        Schema::table('student_import_rows', fn (Blueprint $table) => $table->dropColumn('corrections'));
    }
};
