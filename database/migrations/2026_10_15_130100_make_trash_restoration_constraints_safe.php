<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['establishments', 'academic_years', 'levels', 'mentions', 'programs', 'scholarship_campaigns', 'student_imports', 'students', 'student_records', 'scholarship_applications'] as $name) {
            if (! Schema::hasColumn($name, 'trash_batch')) {
                Schema::table($name, fn (Blueprint $table) => $table->uuid('trash_batch')->nullable()->index());
            }
        }
        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropUnique(['current_marker']);
            $table->dropColumn('current_marker');
        });
        Schema::table('academic_years', fn (Blueprint $table) => $table->unsignedTinyInteger('current_marker')->nullable()->virtualAs('CASE WHEN is_current = 1 AND deleted_at IS NULL THEN 1 ELSE NULL END')->unique());
        if (! Schema::hasIndex('student_records', 'student_records_trash_student_index')) {
            Schema::table('student_records', fn (Blueprint $table) => $table->index('student_id', 'student_records_trash_student_index'));
        }
        if (Schema::hasIndex('student_records', 'student_records_one_active_year')) {
            Schema::table('student_records', fn (Blueprint $table) => $table->dropUnique('student_records_one_active_year'));
        }
        Schema::table('student_records', function (Blueprint $table) {
            $table->dropUnique('student_records_one_establishment_year');
            $table->dropColumn('active_academic_year_id');
        });
        Schema::table('student_records', function (Blueprint $table) {
            $table->unsignedBigInteger('active_academic_year_id')->nullable()->virtualAs("CASE WHEN status = 'active' AND deleted_at IS NULL THEN academic_year_id ELSE NULL END");
            $table->unsignedTinyInteger('live_marker')->nullable()->virtualAs('CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END');
            $table->unique(['student_id', 'active_academic_year_id'], 'student_records_one_active_year');
            $table->unique(['student_id', 'academic_year_id', 'establishment_id', 'live_marker'], 'student_records_one_establishment_year');
        });
    }

    public function down(): void
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->dropUnique('student_records_one_active_year');
            $table->dropUnique('student_records_one_establishment_year');
            $table->dropColumn(['active_academic_year_id', 'live_marker']);
        });
        Schema::table('student_records', function (Blueprint $table) {
            $table->unsignedBigInteger('active_academic_year_id')->nullable()->virtualAs("CASE WHEN status = 'active' THEN academic_year_id ELSE NULL END");
            $table->unique(['student_id', 'active_academic_year_id'], 'student_records_one_active_year');
            $table->unique(['student_id', 'academic_year_id', 'establishment_id'], 'student_records_one_establishment_year');
        });
        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropUnique(['current_marker']);
            $table->dropColumn('current_marker');
        });
        Schema::table('academic_years', fn (Blueprint $table) => $table->unsignedTinyInteger('current_marker')->nullable()->virtualAs('CASE WHEN is_current = 1 THEN 1 ELSE NULL END')->unique());
        foreach (['establishments', 'academic_years', 'levels', 'mentions', 'programs', 'scholarship_campaigns', 'student_imports', 'students', 'student_records', 'scholarship_applications'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('trash_batch'));
        }
    }
};
