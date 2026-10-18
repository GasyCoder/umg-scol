<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'students.notify';

    /**
     * Emails asking a student to complete or check their record (ADR-088): history of each sending,
     * field names only, never values. Permission for the superadmin (and developer) role, others through the matrix.
     */
    public function up(): void
    {
        Schema::create('student_information_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            // Sent from a scholarship file (« Demander une correction » or its page), when there is one.
            $table->foreignId('scholarship_application_id')->nullable()->constrained()->nullOnDelete();
            // Names of the fields listed in the email (StudentCorrectionField values).
            $table->json('fields');
            $table->text('message')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['student_id', 'created_at']);
        });

        $tables = config('permission.table_names');
        $guard = config('access.guard', 'web');
        $permission = DB::table($tables['permissions'])->where('name', self::PERMISSION)->where('guard_name', $guard)->value('id')
            ?? DB::table($tables['permissions'])->insertGetId(['name' => self::PERMISSION, 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
        foreach (DB::table($tables['roles'])->whereIn('name', ['superadmin', 'developer'])->where('guard_name', $guard)->pluck('id') as $role) {
            DB::table($tables['role_has_permissions'])->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $tables = config('permission.table_names');
        $permission = DB::table($tables['permissions'])->where('name', self::PERMISSION)->where('guard_name', config('access.guard', 'web'))->value('id');
        if ($permission !== null) {
            DB::table($tables['role_has_permissions'])->where('permission_id', $permission)->delete();
            DB::table($tables['model_has_permissions'])->where('permission_id', $permission)->delete();
            DB::table($tables['permissions'])->where('id', $permission)->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Schema::dropIfExists('student_information_requests');
    }
};
