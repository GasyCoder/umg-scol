<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('field', 40);
            $table->text('proposed_value');
            $table->char('current_fingerprint', 64);
            $table->string('status', 20)->default('pending');
            $table->string('student_message', 500)->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
            // NULL unless pending: at most one pending request per student and field.
            $table->unsignedTinyInteger('pending_marker')->nullable()->virtualAs("CASE WHEN status = 'pending' THEN 1 ELSE NULL END");
            $table->unique(['student_id', 'field', 'pending_marker'], 'student_corrections_pending_field_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_correction_requests');
    }
};
