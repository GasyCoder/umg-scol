<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Optional logo, stored on the private disk and served to authorized staff only. */
    public function up(): void
    {
        Schema::table('establishments', fn (Blueprint $table) => $table->string('logo_path')->nullable()->after('email'));
    }

    public function down(): void
    {
        Schema::table('establishments', fn (Blueprint $table) => $table->dropColumn('logo_path'));
    }
};
