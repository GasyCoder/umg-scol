<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** How the student identified the record (CIN, phone, email or name), shown to the reviewer. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_link_requests', fn (Blueprint $table) => $table->string('identifier_type', 20)->nullable()->after('student_id'));
    }

    public function down(): void
    {
        Schema::table('student_link_requests', fn (Blueprint $table) => $table->dropColumn('identifier_type'));
    }
};
