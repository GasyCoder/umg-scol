<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** Tables whose records are identified in URLs (students already have their uuid). */
    private const TABLES = [
        'establishments', 'academic_years', 'levels', 'mentions', 'programs',
        'student_records', 'student_imports', 'student_correction_requests', 'student_link_requests',
        'scholarship_campaigns', 'scholarship_applications', 'activity_log',
    ];

    /**
     * Public identifier in URLs: not guessable and not revealing the volume of records.
     * The integer id stays the primary key and the target of every foreign key.
     */
    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->uuid('uuid')->nullable()->after('id'));
            DB::table($name)->select('id')->whereNull('uuid')->orderBy('id')->chunkById(500, function ($rows) use ($name) {
                foreach ($rows as $row) {
                    DB::table($name)->where('id', $row->id)->update(['uuid' => (string) Str::uuid7()]);
                }
            });
            Schema::table($name, function (Blueprint $table) {
                $table->uuid('uuid')->nullable(false)->change();
                $table->unique('uuid');
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['uuid']);
                $table->dropColumn('uuid');
            });
        }
    }
};
