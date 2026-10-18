<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Student portal bell (ADR-089): events after this moment are unread. The feed itself is derived, never copied. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('portal_notifications_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('portal_notifications_seen_at');
        });
    }
};
