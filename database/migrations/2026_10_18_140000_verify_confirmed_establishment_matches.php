<?php

use App\Enums\SituationVerification;
use App\Enums\StudentRecordStatus;
use App\Models\StudentRecord;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            StudentRecord::query()->where('source', 'central')->where('verification', 'matched')
                ->whereHas('student', fn ($q) => $q->where('is_active', true))
                ->whereNotExists(function ($q) {
                    $q->selectRaw('1')->from('student_records as active_record')
                        ->whereColumn('active_record.student_id', 'student_records.student_id')
                        ->whereColumn('active_record.academic_year_id', 'student_records.academic_year_id')
                        ->whereColumn('active_record.id', '!=', 'student_records.id')
                        ->where('active_record.status', 'active')->whereNull('active_record.deleted_at');
                })
                // The historical import must be complete and concern the same year and establishment.
                ->whereExists(function ($q) {
                    $q->selectRaw('1')->from('student_imports')
                        ->whereColumn('student_imports.id', 'student_records.matched_import_id')
                        ->whereColumn('student_imports.academic_year_id', 'student_records.academic_year_id')
                        ->where('student_imports.kind', 'establishment')->where('student_imports.status', 'completed')->whereNotNull('student_imports.committed_at')
                        ->where(fn ($scope) => $scope->whereNull('student_imports.establishment_id')->orWhereColumn('student_imports.establishment_id', 'student_records.establishment_id'));
                })
                // A marker alone is insufficient: retain the valid row which actually matched this record.
                ->whereExists(function ($q) {
                    $q->selectRaw('1')->from('student_import_rows')
                        ->whereColumn('student_import_rows.student_import_id', 'student_records.matched_import_id')
                        ->whereColumn('student_import_rows.matched_student_record_id', 'student_records.id')
                        ->whereColumn('student_import_rows.matched_student_id', 'student_records.student_id')
                        ->where('student_import_rows.status', 'valid')->where('student_import_rows.resolution', 'matched');
                })->lockForUpdate()->chunkById(200, function ($records) {
                    foreach ($records as $record) {
                        $record->update(['verification' => SituationVerification::Verified,
                            ...($record->status === StudentRecordStatus::Pending ? ['status' => StudentRecordStatus::Active] : [])]);
                        activity('student_records')->causedByAnonymous()->performedOn($record)->event('verified_by_import')
                            ->withProperties(['import_id' => $record->matched_import_id, 'changed_fields' => array_values(array_intersect(['verification', 'status'], array_keys($record->getChanges())))])
                            ->log('verified_by_import');
                    }
                });
        });
    }

    public function down(): void
    {
        // A confirmed situation and its audit must not be invalidated by a schema rollback.
    }
};
