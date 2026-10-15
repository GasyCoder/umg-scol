<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Internal account management: public uuid for URLs, suspension flag, and per-user
     * withdrawals of role permissions (additions use Spatie direct permissions).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->boolean('is_active')->default(true)->after('establishment_id');
        });
        DB::table('users')->select('id')->whereNull('uuid')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('users')->where('id', $row->id)->update(['uuid' => (string) Str::uuid7()]);
            }
        });
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid');
        });

        Schema::create('user_denied_permissions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['user_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_denied_permissions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'is_active']);
        });
    }
};
