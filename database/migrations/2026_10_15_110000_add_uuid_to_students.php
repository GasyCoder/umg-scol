<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Public identifier of a student in URLs: not guessable and not revealing the volume of records.
     * The integer id stays the primary key and the target of every foreign key.
     */
    public function up(): void
    {
        Schema::table('students', fn (Blueprint $table) => $table->uuid('uuid')->nullable()->after('id'));
        DB::table('students')->whereNull('uuid')->orderBy('id')->chunkById(500, function ($students) {
            foreach ($students as $student) {
                DB::table('students')->where('id', $student->id)->update(['uuid' => (string) Str::uuid7()]);
            }
        });
        Schema::table('students', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
