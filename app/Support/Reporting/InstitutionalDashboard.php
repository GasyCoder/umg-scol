<?php

namespace App\Support\Reporting;

use App\Enums\StudentImportStatus;
use App\Models\Activity;
use App\Models\ScholarshipApplication;
use App\Models\Student;
use App\Models\StudentCorrectionRequest;
use App\Models\StudentImport;
use App\Models\StudentLinkRequest;
use App\Support\Audit\AuditPresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Role-aware dashboard. Each block is computed only when the existing Policy allows the underlying
 * module, inside the ReportScope perimeter; the client never decides what it may see.
 */
final class InstitutionalDashboard
{
    private InstitutionalMetrics $metrics;

    public function __construct(private readonly ReportScope $scope)
    {
        $this->metrics = new InstitutionalMetrics($scope);
    }

    /** @return array<string,mixed> */
    public function build(): array
    {
        $user = $this->scope->user;
        $can = [
            'students' => Gate::forUser($user)->allows('viewAny', Student::class),
            'imports' => Gate::forUser($user)->allows('viewAny', StudentImport::class),
            'corrections' => Gate::forUser($user)->allows('viewAny', StudentCorrectionRequest::class),
            'links' => Gate::forUser($user)->allows('viewAny', StudentLinkRequest::class),
            'scholarships' => Gate::forUser($user)->allows('viewAny', ScholarshipApplication::class),
            'activity' => Gate::forUser($user)->allows('viewAny', Activity::class),
            'administration' => $this->scope->isGlobal() && $user->can('users.manage'),
            'technical' => $user->hasRole('developer'),
        ];
        $students = $can['students'] ? $this->metrics->students() : null;
        $imports = $can['imports'] ? $this->metrics->imports() : null;
        $corrections = $can['corrections'] ? $this->metrics->corrections() : null;
        $links = $can['links'] ? $this->metrics->pendingLinkRequests() : null;
        $confirmations = $can['students'] ? $this->metrics->confirmations() : null;
        $scholarships = $can['scholarships'] ? $this->metrics->scholarships() : null;
        $campaigns = $can['scholarships'] || $user->can('campaigns.manage') ? $this->metrics->campaigns() : null;
        $establishments = $this->scope->isGlobal() && $can['students'] ? $this->metrics->activeEstablishments() : null;
        $users = $can['administration'] ? $this->metrics->internalUsers() : null;

        return [
            'scope' => ['year' => $this->scope->yearSummary(), 'global' => $this->scope->isGlobal(), 'establishment' => $this->scope->isGlobal() ? null : $user->establishment()->value('name')],
            'kpis' => $this->kpis($students, $imports, $corrections, $links, $scholarships, $establishments, $users),
            'watch' => $this->watch($imports, $corrections, $links, $scholarships, $confirmations),
            'charts' => [
                'students_by_level' => $can['students'] ? $this->metrics->studentsByLevel() : null,
                'students_by_program' => $can['students'] && ! $this->scope->isGlobal() ? $this->metrics->studentsByProgram() : null,
                'students_by_establishment' => $can['students'] && $this->scope->isGlobal() ? $this->metrics->studentsByEstablishment() : null,
                'scholarships_by_status' => $scholarships['by_status'] ?? null,
                'scholarships_by_establishment' => $can['scholarships'] ? $this->metrics->scholarshipsByEstablishment() : null,
            ],
            'confirmations' => $confirmations,
            'corrections' => $corrections,
            'imports' => $imports === null ? null : [...$imports, 'recent' => $this->metrics->recentImports()],
            'campaigns' => $campaigns,
            'scholarships' => $scholarships,
            'technical' => $can['technical'] ? $this->technical() : null,
            'activity' => $can['activity'] ? Activity::query()->with('causer.roles')->latest('id')->limit(8)->get()->map(fn (Activity $a) => AuditPresenter::summary($a))->values()->all() : null,
        ];
    }

    /**
     * Four to eight headline figures, chosen by what the role is allowed to see.
     *
     * @param  array<string,int>|null  $students
     * @param  array<string,int>|null  $imports
     * @param  array<string,int>|null  $corrections
     * @param  array{total:int,today:int,by_status:array<string,int>}|null  $scholarships
     * @return array<int,array{key:string,label:string,value:int,hint:string,href:string|null}>
     */
    private function kpis(?array $students, ?array $imports, ?array $corrections, ?int $links, ?array $scholarships, ?int $establishments, ?int $users): array
    {
        $year = $this->scope->year->name ?? 'aucune année courante';
        $cards = [];
        if ($students !== null) {
            $cards[] = ['key' => 'active_students', 'label' => 'Étudiants actifs', 'value' => $students['active_students'], 'hint' => 'Identités actives déclarées en '.$year, 'href' => '/students'];
            $cards[] = ['key' => 'active_declarations', 'label' => 'Déclarations actives', 'value' => $students['active_declarations'], 'hint' => 'Déclarations annuelles '.$year, 'href' => '/students'];
        }
        if ($establishments !== null) {
            $cards[] = ['key' => 'establishments', 'label' => 'Établissements actifs', 'value' => $establishments, 'hint' => 'Référentiel institutionnel', 'href' => null];
        }
        if ($scholarships !== null) {
            $cards[] = ['key' => 'applications', 'label' => 'Dossiers de bourse reçus', 'value' => $scholarships['total'], 'hint' => 'Campagnes '.$year, 'href' => '/scholarships/applications'];
            $cards[] = ['key' => 'applications_pending', 'label' => 'Dossiers à traiter', 'value' => $scholarships['by_status']['pending'] + $scholarships['by_status']['in_review'] + $scholarships['by_status']['needs_correction'], 'hint' => 'Reçus, en vérification ou en correction', 'href' => '/scholarships/applications'];
            $cards[] = ['key' => 'applications_today', 'label' => 'Reçus aujourd’hui', 'value' => $scholarships['today'], 'hint' => 'Dossiers papier enregistrés ce jour', 'href' => '/scholarships/applications'];
        }
        if ($corrections !== null) {
            $cards[] = ['key' => 'corrections_pending', 'label' => 'Corrections en attente', 'value' => $corrections['pending'], 'hint' => 'Demandes étudiantes à examiner', 'href' => '/corrections?status=pending'];
        }
        if ($links !== null) {
            $cards[] = ['key' => 'links_pending', 'label' => 'Rattachements en attente', 'value' => $links, 'hint' => 'Comptes étudiants à vérifier', 'href' => '/student-links'];
        }
        if ($imports !== null) {
            $cards[] = ['key' => 'imports_anomalies', 'label' => 'Imports avec anomalies', 'value' => $imports['with_anomalies'], 'hint' => $imports['imports'].' import(s) en '.$year.', '.$imports['failed'].' en échec', 'href' => '/imports'];
        }
        if ($users !== null) {
            $cards[] = ['key' => 'internal_users', 'label' => 'Utilisateurs internes', 'value' => $users, 'hint' => 'Comptes du portail interne', 'href' => null];
        }

        $cards = collect($cards)->keyBy('key');
        $order = match (true) {
            ! $this->scope->isGlobal() => ['active_students', 'active_declarations', 'corrections_pending', 'links_pending', 'imports_anomalies'],
            $users !== null => ['active_students', 'active_declarations', 'establishments', 'internal_users', 'applications', 'applications_pending', 'corrections_pending', 'imports_anomalies'],
            default => ['active_students', 'active_declarations', 'establishments', 'applications', 'applications_pending', 'applications_today', 'corrections_pending', 'links_pending'],
        };

        return collect($order)->filter(fn ($key) => $cards->has($key))->map(fn ($key) => $cards[$key])->values()->all();
    }

    /**
     * Items needing attention: non-zero counters only, each linked to the page that handles them.
     *
     * @param  array<string,int>|null  $imports
     * @param  array<string,int>|null  $corrections
     * @param  array{total:int,today:int,by_status:array<string,int>}|null  $scholarships
     * @param  array{confirmed:int,stale:int,never_confirmed:int,total:int}|null  $confirmations
     * @return array<int,array{label:string,value:int,href:string|null}>
     */
    private function watch(?array $imports, ?array $corrections, ?int $links, ?array $scholarships, ?array $confirmations): array
    {
        return array_values(array_filter([
            ['label' => 'Demandes de correction en attente', 'value' => $corrections['pending'] ?? 0, 'href' => '/corrections?status=pending'],
            ['label' => 'Demandes de rattachement en attente', 'value' => $links ?? 0, 'href' => '/student-links'],
            ['label' => 'Dossiers reçus non encore vérifiés', 'value' => $scholarships['by_status']['pending'] ?? 0, 'href' => '/scholarships/applications?status=pending'],
            ['label' => 'Dossiers en attente de correction', 'value' => $scholarships['by_status']['needs_correction'] ?? 0, 'href' => '/scholarships/applications?status=needs_correction'],
            ['label' => 'Imports en échec', 'value' => $imports['failed'] ?? 0, 'href' => '/imports'],
            ['label' => 'Confirmations étudiantes devenues obsolètes', 'value' => $confirmations['stale'] ?? 0, 'href' => null],
        ], fn (array $item) => $item['value'] > 0));
    }

    /**
     * Technical indicators for the developer role: counts only, never payloads, configuration or secrets.
     *
     * @return array<string,int|string|null>
     */
    private function technical(): array
    {
        $last = Activity::query()->latest('id')->value('created_at');

        return [
            'failed_jobs' => Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : null,
            'failed_imports' => StudentImport::query()->where('status', StudentImportStatus::Failed->value)->count(),
            'audit_entries_24h' => Activity::query()->where('created_at', '>=', now()->subDay())->count(),
            'last_audit_at' => $last === null ? null : Carbon::parse($last)->toIso8601String(),
        ];
    }
}
