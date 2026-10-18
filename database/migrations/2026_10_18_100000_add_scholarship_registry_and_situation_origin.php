<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three ways a student reaches the platform for a year, besides the establishment file:
 * the national scholarship base (PNES: code bourse, UIN, bank account), the entry by the
 * central scholarship office (waiting for the establishment's list), and the pre-registration
 * results of new bacheliers. The situation keeps its origin, its verification and whether the
 * student holds a scholarship that year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('uin', 30)->nullable()->after('cin_issued_place')->index();
            $table->string('scholarship_code', 30)->nullable()->after('uin')->index();
            // Encrypted by the model: bank account numbers never stored in clear.
            $table->text('bank_account')->nullable()->after('scholarship_code');
        });
        Schema::table('student_records', function (Blueprint $table) {
            $table->string('source', 20)->default('establishment')->after('status');
            $table->string('verification', 20)->default('verified')->after('source')->index();
            $table->boolean('is_scholarship_holder')->default(false)->after('verification')->index();
            $table->string('preinscription_reference', 50)->nullable()->after('is_scholarship_holder');
            $table->foreignId('matched_import_id')->nullable()->after('preinscription_reference')->constrained('student_imports')->nullOnDelete();
        });
        Schema::table('student_imports', function (Blueprint $table) {
            $table->string('kind', 20)->default('establishment')->after('id')->index();
            // Existing situations confirmed by the file (found in the list, or marked scholarship holder).
            $table->unsignedInteger('matched_rows')->default(0)->after('declared_rows');
        });
    }

    public function down(): void
    {
        Schema::table('student_imports', fn (Blueprint $table) => $table->dropColumn(['kind', 'matched_rows']));
        Schema::table('student_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('matched_import_id');
            $table->dropColumn(['source', 'verification', 'is_scholarship_holder', 'preinscription_reference']);
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['uin']);
            $table->dropIndex(['scholarship_code']);
            $table->dropColumn(['uin', 'scholarship_code', 'bank_account']);
        });
    }
};
