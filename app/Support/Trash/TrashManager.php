<?php

namespace App\Support\Trash;

use App\Enums\ScholarshipApplicationStatus;
use App\Enums\StudentImportStatus;
use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Mention;
use App\Models\Program;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipCampaign;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\StudentIdentityLocks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrashManager
{
    private static int $depth = 0;

    public static function isRunning(): bool
    {
        return self::$depth > 0;
    }

    public function handle(User $actor, string $kind, string $uuid, string $operation): void
    {
        Gate::forUser($actor)->authorize($operation === 'purge' ? 'trash.purge-ui' : 'trash.manage-ui');
        abort_unless(in_array($operation, ['trash', 'restore', 'purge'], true), 404);
        try {
            DB::transaction(function () use ($actor, $kind, $uuid, $operation) {
                $target = TrashCatalogue::model($kind)->newQuery()->withoutGlobalScope(SoftDeletingScope::class)->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
                if ($operation === 'trash' && $target->getAttribute('deleted_at') !== null) {
                    return;
                }
                if ($operation === 'restore' && $target->getAttribute('deleted_at') === null) {
                    return;
                }
                if ($operation === 'purge' && $target->getAttribute('deleted_at') === null) {
                    $this->fail('Déplacez d’abord cet élément dans la corbeille.');
                }
                self::$depth++;
                try {
                    if ($operation === 'trash') {
                        $this->move($actor, $target, (string) Str::uuid());
                    }
                    if ($operation === 'restore') {
                        $this->restore($actor, $target);
                    }
                    if ($operation === 'purge') {
                        $this->purge($actor, $target);
                    }
                } finally {
                    self::$depth--;
                }
            }, 5);
        } catch (QueryException $error) {
            if ($error->getCode() !== '23000' && $error->getCode() !== '23505') {
                throw $error;
            }
            $this->fail('Une donnée liée ou un conflit empêche cette opération. Aucun changement n’a été conservé.');
        }
    }

    private function move(User $actor, Model $target, string $batch): void
    {
        $reason = $this->blocked($target, false);
        if ($reason !== null) {
            $this->fail($reason);
        }
        if ($target instanceof Student) {
            foreach (StudentRecord::query()->where('student_id', $target->id)->orderBy('id')->lockForUpdate()->get() as $record) {
                $this->move($actor, $record, $batch);
            }
        }
        $target->forceFill(['deleted_by' => $actor->id, 'trash_batch' => $batch])->save();
        $target->delete();
        $this->audit($actor, $target, 'trashed', $batch);
    }

    private function restore(User $actor, Model $target): void
    {
        if ($target instanceof Student) {
            StudentIdentityLocks::acquire(StudentIdentityLocks::keys($target->attributesToArray()));
            foreach (StudentIdentityLocks::uniqueValues($target->attributesToArray()) as $field => $value) {
                if (Student::query()->where($field, $value)->whereKeyNot($target->id)->lockForUpdate()->first() !== null) {
                    $this->fail('Une identité correspondante existe déjà. Résolvez le doublon avant la restauration.');
                }
            }
            if ($target->birth_date !== null && Student::query()->where('last_name', $target->last_name)->where('first_names', $target->first_names)->whereDate('birth_date', $target->birth_date)->whereKeyNot($target->id)->lockForUpdate()->first() !== null) {
                $this->fail('Une identité de mêmes nom, prénoms et naissance existe déjà.');
            }
        }
        if ($target instanceof AcademicYear && $target->is_current && AcademicYear::query()->where('is_current', true)->lockForUpdate()->first() !== null) {
            $this->fail('Une autre année est courante. Retirez ce statut avant de restaurer cette année.');
        }
        if ($target instanceof StudentRecord) {
            $this->parents($target, ['student_id' => Student::class, 'academic_year_id' => AcademicYear::class, 'establishment_id' => Establishment::class, 'level_id' => Level::class, 'program_id' => Program::class]);
            $conflict = StudentRecord::query()->where('student_id', $target->student_id)->where('academic_year_id', $target->academic_year_id)->where(function ($q) use ($target) {
                $q->where('establishment_id', $target->establishment_id);
                if ($target->status->value === 'active') {
                    $q->orWhere('status', 'active');
                }
            })->lockForUpdate()->first();
            if ($conflict !== null) {
                $this->fail('Une déclaration de cette année existe déjà ou une autre déclaration est active.');
            }
        }
        if ($target instanceof Program || $target instanceof Mention) {
            $this->parents($target, ['establishment_id' => Establishment::class]);
        }
        if ($target instanceof Program) {
            $this->parents($target, ['mention_id' => Mention::class]);
        }
        if ($target instanceof StudentImport || $target instanceof ScholarshipCampaign) {
            $this->parents($target, ['establishment_id' => Establishment::class, 'academic_year_id' => AcademicYear::class]);
        }
        if ($target instanceof ScholarshipApplication) {
            $this->parents($target, ['student_id' => Student::class, 'student_record_id' => StudentRecord::class, 'scholarship_campaign_id' => ScholarshipCampaign::class]);
        }
        $batch = (string) $target->getAttribute('trash_batch');
        $target->forceFill(['deleted_by' => null]);
        if (method_exists($target, 'restore')) {
            $target->restore();
        }
        $this->audit($actor, $target, 'restored', $batch);
        if ($target instanceof Student) {
            foreach (StudentRecord::query()->withoutGlobalScope(SoftDeletingScope::class)->whereNotNull('deleted_at')->where('student_id', $target->id)->where('trash_batch', $batch)->orderBy('id')->lockForUpdate()->get() as $record) {
                $this->restore($actor, $record);
            }
        }
    }

    /** @param array<string,class-string<Model>> $parents */
    private function parents(Model $target, array $parents): void
    {
        foreach ($parents as $field => $class) {
            $id = $target->getAttribute($field);
            if ($id !== null && $class::query()->whereKey($id)->sharedLock()->first() === null) {
                $this->fail('Restaurez d’abord les données parentes nécessaires à cet élément.');
            }
        }
    }

    private function purge(User $actor, Model $target): void
    {
        $reason = $this->blocked($target, true);
        if ($reason !== null) {
            $this->fail($reason);
        }
        if ($target instanceof Student) {
            foreach (StudentRecord::query()->withoutGlobalScope(SoftDeletingScope::class)->where('student_id', $target->id)->orderBy('id')->lockForUpdate()->get() as $record) {
                if ($record->deleted_at === null) {
                    $this->fail('Toutes les déclarations doivent être en corbeille.');
                } $this->purge($actor, $record);
            }
        }
        if ($target instanceof ScholarshipApplication) {
            if ($target->status !== ScholarshipApplicationStatus::Pending || $target->history()->count() > 1 || $target->checks()->whereNotNull('checked_at')->exists()) {
                $this->fail('Un dossier déjà traité ne peut pas être purgé. Conservez-le dans la corbeille ou restaurez-le.');
            }
            $target->checks()->delete();
            $target->history()->delete();
        }
        [$disk, $path] = match (true) {
            $target instanceof StudentImport => [$target->disk, $target->stored_path],
            $target instanceof Establishment && $target->logo_path !== null => [Establishment::LOGO_DISK, $target->logo_path],
            default => [null, null],
        };
        $this->audit($actor, $target, 'purged', (string) $target->getAttribute('trash_batch'));
        $target->forceDelete();
        // Files are removed only after the database transaction has committed.
        if ($disk !== null) {
            app(FileDeletionOutbox::class)->schedule($disk, $path);
        }
    }

    /** A used referential remains available for history, including trashed children. */
    public function blocked(Model $target, bool $purge): ?string
    {
        $links = match (true) {
            $target instanceof Establishment => [['programs', 'establishment_id'], ['mentions', 'establishment_id'], ['users', 'establishment_id'], ['student_records', 'establishment_id'], ['student_imports', 'establishment_id']],
            $target instanceof AcademicYear => [['student_records', 'academic_year_id'], ['student_imports', 'academic_year_id'], ['scholarship_campaigns', 'academic_year_id'], ['student_information_confirmations', 'academic_year_id']],
            $target instanceof Level => [['student_records', 'level_id']],
            $target instanceof Mention => [['programs', 'mention_id']],
            $target instanceof Program => [['student_records', 'program_id']],
            $target instanceof ScholarshipCampaign => [['scholarship_applications', 'scholarship_campaign_id']],
            $target instanceof Student && $purge => [['users', 'student_id'], ['student_link_requests', 'student_id'], ['student_correction_requests', 'student_id'], ['student_information_confirmations', 'student_id'], ['student_import_rows', 'matched_student_id'], ['scholarship_applications', 'student_id']],
            $target instanceof StudentRecord && $purge => [['scholarship_applications', 'student_record_id'], ['student_information_confirmations', 'student_record_id'], ['student_import_rows', 'matched_student_record_id']],
            default => [],
        };
        foreach ($links as [$table,$key]) {
            if (DB::table($table)->where($key, $target->getKey())->lockForUpdate()->first() !== null) {
                return 'Cet élément est utilisé par des données liées. Utilisez la désactivation ou l’archivage ; la suppression est refusée.';
            }
        }
        if (($target instanceof Student || $target instanceof StudentRecord) && ! $purge) {
            $key = $target instanceof Student ? 'student_id' : 'student_record_id';
            if (ScholarshipApplication::query()->where($key, $target->getKey())->lockForUpdate()->first() !== null) {
                return 'Un dossier de bourse visible utilise cet élément. Traitez d’abord ce dossier.';
            }
        }
        if ($target instanceof StudentImport && in_array($target->status, [StudentImportStatus::Analyzing, StudentImportStatus::Committing], true)) {
            return 'Un import en cours de traitement ne peut pas être supprimé.';
        }

        return null;
    }

    private function audit(User $actor, Model $target, string $event, string $batch): void
    {
        activity('trash')->causedBy($actor)->performedOn($target)->event($event)->withProperties(['kind' => array_search($target::class, TrashCatalogue::MODELS, true), 'trash_batch' => $batch])->log($event);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['trash' => $message]);
    }
}
