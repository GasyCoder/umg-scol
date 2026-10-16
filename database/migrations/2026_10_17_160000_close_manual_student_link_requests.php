<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Linking is now automatic (OTP + information of the record): requests still waiting for the
 * former manual review are closed; the student links again from the portal.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('student_link_requests')->where('status', 'pending')->update(['status' => 'cancelled', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Closed requests are not reopened.
    }
};
