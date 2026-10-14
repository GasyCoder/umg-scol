<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->unique()->constrained('students')->restrictOnDelete();
            $table->string('normalized_email')->virtualAs('LOWER(email)')->unique();
        });
        Schema::create('portal_email_locks', fn (Blueprint $table) => $table->string('key', 64)->primary());
        Schema::create('email_otp_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email');
            $table->string('email_key', 64)->unique();
            $table->string('code_hash');
            $table->string('session_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('provider', 30);
            $table->string('provider_user_id', 191);
            $table->string('provider_email');
            $table->timestamps();
            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['user_id', 'provider']);
        });
        Schema::create('student_link_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamp('requested_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['status', 'student_id']);
            $table->unique(['user_id', 'student_id']);
            $table->unsignedBigInteger('pending_user_id')->nullable()->virtualAs("CASE WHEN status = 'pending' THEN user_id ELSE NULL END")->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_link_requests');
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('email_otp_challenges');
        Schema::dropIfExists('portal_email_locks');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropUnique(['student_id']);
            $table->dropUnique(['normalized_email']);
            $table->dropColumn(['student_id', 'normalized_email']);
        });
    }
};
