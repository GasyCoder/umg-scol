<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('disk', 30)->default('student_imports');
            $table->string('status', 20)->default('uploaded');
            $table->json('headers')->nullable();
            $table->json('mapping')->nullable();
            foreach (['total', 'valid', 'warning', 'rejected', 'duplicate', 'created', 'updated', 'declared', 'skipped'] as $counter) {
                $table->unsignedInteger($counter.'_rows')->default(0);
            }
            foreach (['started', 'analyzed', 'committed', 'failed'] as $date) {
                $table->timestamp($date.'_at')->nullable();
            }
            $table->string('failure_reason')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();
            $table->index(['establishment_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
        Schema::create('student_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->json('normalized_data')->nullable();
            $table->string('status', 20)->default('rejected');
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->foreignId('matched_student_id')->nullable()->constrained('students')->restrictOnDelete();
            $table->foreignId('matched_student_record_id')->nullable()->constrained('student_records')->restrictOnDelete();
            $table->string('resolution', 30)->nullable();
            $table->timestamps();
            $table->unique(['student_import_id', 'row_number']);
            $table->index(['student_import_id', 'status', 'id']);
        });
        Schema::create('student_identity_locks', function (Blueprint $table) {
            $table->string('key', 64)->primary();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_identity_locks');
        Schema::dropIfExists('student_import_rows');
        Schema::dropIfExists('student_imports');
    }
};
