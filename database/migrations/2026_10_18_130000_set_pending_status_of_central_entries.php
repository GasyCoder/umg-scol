<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Status « pending » of the situations entered by the central office: active only once validated.
     * Central entries not validated yet were created « active »: they now wait for their validation.
     */
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE student_records MODIFY status ENUM('active', 'inactive', 'pending') NOT NULL DEFAULT 'active'");
        }
        DB::table('student_records')->where('source', 'central')->where('verification', '!=', 'verified')->where('status', 'active')->update(['status' => 'pending']);
    }

    public function down(): void
    {
        DB::table('student_records')->where('status', 'pending')->update(['status' => 'active']);
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE student_records MODIFY status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
        }
    }
};
