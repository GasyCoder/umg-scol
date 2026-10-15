<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Student data follows the official establishment file (modèle établissement):
 * identity without matricule / BAC number / birth place / BAC mention, plus sex, mother's name
 * and nationality; annual declaration with the repeat code and the scholarship request deposit;
 * referentials for domain (mention), training type (program), sector (establishment) and the
 * BAC year limit of the scholarship rule (academic year).
 */
return new class extends Migration
{
    private const REMOVED_STUDENT_FIELDS = ['student_number', 'birth_place', 'bac_registration_number', 'bac_mention'];

    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->index(['is_active', 'sort_order']);
            $table->timestamps();
        });
        Schema::table('mentions', function (Blueprint $table) {
            $table->foreignId('domain_id')->nullable()->after('establishment_id')->constrained()->restrictOnDelete();
        });
        Schema::table('programs', fn (Blueprint $table) => $table->string('training_type', 20)->nullable()->after('name'));
        Schema::table('establishments', fn (Blueprint $table) => $table->string('sector', 20)->nullable()->after('type'));
        Schema::table('academic_years', fn (Blueprint $table) => $table->unsignedSmallInteger('scholarship_max_bac_year')->nullable()->after('ends_at'));

        // The removed values no longer exist anywhere: their correction requests go with them.
        DB::table('student_correction_requests')->whereIn('field', self::REMOVED_STUDENT_FIELDS)->delete();
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['student_number']);
            $table->dropIndex(['bac_registration_number']);
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(self::REMOVED_STUDENT_FIELDS);
        });
        Schema::table('students', function (Blueprint $table) {
            $table->char('sex', 1)->nullable()->after('first_names');
            $table->string('mother_name')->nullable()->after('birth_date');
            $table->string('nationality', 50)->nullable()->after('cin_issued_place');
        });

        Schema::table('student_records', function (Blueprint $table) {
            $table->string('repeat_code', 3)->nullable()->after('program_id');
            $table->date('scholarship_requested_at')->nullable()->after('repeat_code');
            $table->string('scholarship_request_reference', 50)->nullable()->after('scholarship_requested_at');
            // A request reference is given once per establishment and year (live declarations only).
            $table->unique(['academic_year_id', 'establishment_id', 'scholarship_request_reference', 'live_marker'], 'student_records_request_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->dropUnique('student_records_request_reference_unique');
            $table->dropColumn(['repeat_code', 'scholarship_requested_at', 'scholarship_request_reference']);
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['sex', 'mother_name', 'nationality']);
        });
        Schema::table('students', function (Blueprint $table) {
            $table->string('student_number', 100)->nullable()->index();
            $table->string('birth_place')->nullable();
            $table->string('bac_registration_number', 100)->nullable()->index();
            $table->string('bac_mention', 100)->nullable();
        });
        Schema::table('academic_years', fn (Blueprint $table) => $table->dropColumn('scholarship_max_bac_year'));
        Schema::table('establishments', fn (Blueprint $table) => $table->dropColumn('sector'));
        Schema::table('programs', fn (Blueprint $table) => $table->dropColumn('training_type'));
        Schema::table('mentions', fn (Blueprint $table) => $table->dropConstrainedForeignId('domain_id'));
        Schema::dropIfExists('domains');
    }
};
