<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 100)->nullable()->index();
            $table->string('last_name', 150)->index();
            $table->string('first_names', 150)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('cin', 100)->nullable()->index();
            $table->date('cin_issued_at')->nullable();
            $table->string('cin_issued_place')->nullable();
            $table->string('phone', 30)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->text('address')->nullable();
            $table->string('bac_registration_number', 100)->nullable()->index();
            $table->string('bac_series', 50)->nullable();
            $table->unsignedSmallInteger('bac_year')->nullable();
            $table->string('bac_mention', 100)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->index(['last_name', 'first_names', 'birth_date']);
            $table->timestamps();
        });
        Schema::table('programs', fn (Blueprint $table) => $table->unique(['id', 'establishment_id'], 'programs_id_establishment_unique'));
        Schema::create('student_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('establishment_id')->constrained()->restrictOnDelete();
            $table->foreignId('level_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->nullable();
            $table->foreign(['program_id', 'establishment_id'], 'student_records_program_establishment_foreign')->references(['id', 'establishment_id'])->on('programs')->restrictOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('declared_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('declared_at');
            $table->unsignedBigInteger('active_academic_year_id')->nullable()->virtualAs("CASE WHEN status = 'active' THEN academic_year_id ELSE NULL END");
            $table->unique(['student_id', 'active_academic_year_id'], 'student_records_one_active_year');
            $table->unique(['student_id', 'academic_year_id', 'establishment_id'], 'student_records_one_establishment_year');
            $table->index(['academic_year_id', 'establishment_id', 'status']);
            $table->index(['establishment_id', 'level_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_records');
        Schema::table('programs', fn (Blueprint $table) => $table->dropUnique('programs_id_establishment_unique'));
        Schema::dropIfExists('students');
    }
};
