<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scholarship_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->string('notes', 1000)->nullable();
            $table->unsignedInteger('last_application_sequence')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['academic_year_id', 'name'], 'scholarship_campaigns_year_name_unique');
        });
        Schema::create('scholarship_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number', 30)->unique();
            $table->foreignId('scholarship_campaign_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_record_id')->constrained()->restrictOnDelete();
            $table->string('ddb_number', 100)->nullable();
            $table->string('ddb_key', 100)->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at');
            $table->foreignId('review_started_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('review_started_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_reason', 1000)->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->unique(['scholarship_campaign_id', 'student_id'], 'scholarship_applications_campaign_student_unique');
            $table->unique(['scholarship_campaign_id', 'sequence'], 'scholarship_applications_campaign_sequence_unique');
            // NULL keys are allowed many times: only a provided DDB number must be unique in its campaign.
            $table->unique(['scholarship_campaign_id', 'ddb_key'], 'scholarship_applications_campaign_ddb_unique');
            $table->index(['scholarship_campaign_id', 'status', 'received_at'], 'scholarship_applications_campaign_status_index');
            $table->index(['status', 'received_at']);
            $table->index('received_at');
            $table->index('ddb_key');
        });
        Schema::create('scholarship_application_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scholarship_application_id')->constrained(indexName: 'scholarship_checks_application_foreign')->restrictOnDelete();
            $table->string('key', 50);
            $table->string('status', 20)->default('pending');
            $table->string('note', 500)->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['scholarship_application_id', 'key'], 'scholarship_checks_application_key_unique');
        });
        Schema::create('scholarship_application_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scholarship_application_id')->constrained(indexName: 'scholarship_history_application_foreign')->restrictOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 1000)->nullable();
            $table->timestamp('created_at');
            $table->index(['scholarship_application_id', 'id'], 'scholarship_history_application_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholarship_application_status_histories');
        Schema::dropIfExists('scholarship_application_checks');
        Schema::dropIfExists('scholarship_applications');
        Schema::dropIfExists('scholarship_campaigns');
    }
};
