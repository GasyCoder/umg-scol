<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_information_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            // Position in the student's history: a concurrent duplicate confirmation hits the unique key.
            $table->unsignedInteger('sequence');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_record_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            // Keyed HMAC of the confirmed version, never a copy of the values.
            $table->char('information_fingerprint', 64);
            $table->timestamp('confirmed_at');
            $table->timestamps();
            $table->unique(['student_id', 'sequence'], 'student_confirmations_student_sequence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_information_confirmations');
    }
};
