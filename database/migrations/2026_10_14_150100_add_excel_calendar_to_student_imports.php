<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_imports', fn (Blueprint $table) => $table->unsignedSmallInteger('excel_calendar')->default(1900));
    }

    public function down(): void
    {
        Schema::table('student_imports', fn (Blueprint $table) => $table->dropColumn('excel_calendar'));
    }
};
