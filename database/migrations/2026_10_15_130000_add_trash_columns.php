<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables whose rows can go to the trash (soft delete), restorable by an authorised user. */
    private const TABLES = ['establishments', 'academic_years', 'levels', 'mentions', 'programs', 'scholarship_campaigns', 'student_imports', 'students', 'student_records', 'scholarship_applications'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->softDeletes();
                $table->foreignId('deleted_by')->nullable()->constrained('users', indexName: $name.'_deleted_by_foreign')->nullOnDelete();
                $table->index('deleted_at');
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropForeign($name.'_deleted_by_foreign');
                $table->dropIndex(['deleted_at']);
                $table->dropColumn(['deleted_by', 'deleted_at']);
            });
        }
    }
};
