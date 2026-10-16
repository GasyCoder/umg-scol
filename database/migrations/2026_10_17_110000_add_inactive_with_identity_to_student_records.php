<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deactivating a student also deactivates the declarations of the current year; this marker
 * lets the reactivation restore exactly those, never a declaration deactivated on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_records', fn (Blueprint $table) => $table->boolean('inactive_with_identity')->default(false)->after('status'));

        // Identities already inactive: same state as if they had been deactivated with the new rule.
        $currentYears = DB::table('academic_years')->where('is_current', true)->where('is_active', true)->whereNull('deleted_at')->pluck('id');
        DB::table('student_records')->where('status', 'active')->whereNull('deleted_at')->whereIn('academic_year_id', $currentYears)
            ->whereIn('student_id', DB::table('students')->where('is_active', false)->select('id'))
            ->update(['status' => 'inactive', 'inactive_with_identity' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('student_records')->where('inactive_with_identity', true)->update(['status' => 'active']);
        Schema::table('student_records', fn (Blueprint $table) => $table->dropColumn('inactive_with_identity'));
    }
};
