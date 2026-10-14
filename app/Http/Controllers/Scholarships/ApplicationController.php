<?php

namespace App\Http\Controllers\Scholarships;

use App\Actions\Scholarships\TransitionScholarshipApplication;
use App\Actions\Scholarships\UpdateScholarshipCheck;
use App\Enums\ScholarshipApplicationStatus;
use App\Enums\ScholarshipCampaignStatus;
use App\Enums\ScholarshipCheckStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scholarships\ApplicationListRequest;
use App\Http\Requests\Scholarships\TransitionApplicationRequest;
use App\Http\Requests\Scholarships\UpdateCheckRequest;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipCampaign;
use App\Support\ScholarshipApplicationFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationController extends Controller
{
    private const LIST_RELATIONS = ['campaign:id,name', 'student:id,student_number,last_name,first_names', 'studentRecord:id,establishment_id,level_id,program_id', 'studentRecord.establishment:id,name', 'studentRecord.level:id,name', 'studentRecord.program:id,name'];

    public function index(ApplicationListRequest $request): Response
    {
        $filters = $request->safe()->only(ScholarshipApplicationFilters::KEYS);
        $query = ScholarshipApplicationFilters::apply(ScholarshipApplication::query()->with(self::LIST_RELATIONS), $filters);
        // Tab counters: same filters except the status itself, in one grouped query.
        $counts = ScholarshipApplicationFilters::apply(ScholarshipApplication::query(), array_diff_key($filters, ['status' => true]))
            ->toBase()->select('status')->selectRaw('COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return Inertia::render('scholarships/applications/index', [
            'applications' => $query->orderByDesc('received_at')->orderByDesc('id')->paginate(25)->withQueryString()->through(fn (ScholarshipApplication $a) => [
                'id' => $a->id, 'uuid' => $a->uuid, 'application_number' => $a->application_number, 'ddb_number' => $a->ddb_number, 'campaign' => $a->campaign->name,
                'student' => $a->student->only(['student_number', 'last_name', 'first_names']), 'establishment' => $a->studentRecord->establishment->name,
                'level' => $a->studentRecord->level->name, 'status' => $a->status->value, 'status_label' => $a->status->label(), 'received_at' => $a->received_at->toIso8601String(),
            ]),
            'filters' => $filters,
            'statusCounts' => collect(ScholarshipApplicationStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            'options' => [
                'campaigns' => ScholarshipCampaign::query()->orderByDesc('id')->get(['id', 'name']),
                'statuses' => array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], ScholarshipApplicationStatus::cases()),
                'establishments' => Establishment::query()->orderBy('name')->get(['id', 'name']),
                'levels' => Level::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            ],
            'canExport' => Gate::allows('export', ScholarshipApplication::class),
            'canReceive' => Gate::allows('receive', ScholarshipApplication::class),
        ]);
    }

    public function show(ScholarshipApplication $application): Response
    {
        Gate::authorize('view', $application);
        $application->load([
            'campaign.academicYear:id,name', 'student:id,uuid,student_number,last_name,first_names,birth_date,birth_place,is_active',
            'studentRecord.establishment:id,name', 'studentRecord.level:id,name', 'studentRecord.program:id,name', 'studentRecord.academicYear:id,name',
            'checks' => fn ($q) => $q->orderBy('id')->with('checker:id,name'), 'history' => fn ($q) => $q->orderBy('id')->with('actor:id,name'), 'receiver:id,name', 'reviewStarter:id,name', 'decider:id,name',
        ]);
        $editable = ! $application->status->isFinal() && $application->campaign->status !== ScholarshipCampaignStatus::Archived;
        $record = $application->studentRecord;

        return Inertia::render('scholarships/applications/show', [
            'application' => [
                'id' => $application->id, 'uuid' => $application->uuid, 'application_number' => $application->application_number, 'ddb_number' => $application->ddb_number,
                'status' => $application->status->value, 'status_label' => $application->status->label(), 'decision_reason' => $application->decision_reason, 'internal_notes' => $application->internal_notes,
                'campaign' => ['name' => $application->campaign->name, 'academic_year' => $application->campaign->academicYear->name, 'status_label' => $application->campaign->status->label()],
                'received' => ['at' => $application->received_at->toIso8601String(), 'by' => $application->receiver->name],
                'review_started' => $application->review_started_at ? ['at' => $application->review_started_at->toIso8601String(), 'by' => $application->reviewStarter?->name] : null,
                'decided' => $application->decided_at ? ['at' => $application->decided_at->toIso8601String(), 'by' => $application->decider?->name] : null,
                'student' => [
                    ...$application->student->only(['uuid', 'student_number', 'last_name', 'first_names', 'birth_place', 'is_active']), 'birth_date' => $application->student->birth_date?->format('Y-m-d'),
                    'academic_year' => $record->academicYear->name, 'establishment' => $record->establishment->name, 'level' => $record->level->name, 'program' => $record->program?->name, 'record_status' => $record->status->value,
                ],
                'checks' => $application->checks->map(fn ($check) => [
                    'key' => $check->key, 'label' => $check->label(), 'status' => $check->status->value, 'status_label' => $check->status->label(), 'note' => $check->note,
                    'checked' => $check->checked_at ? ['at' => $check->checked_at->toIso8601String(), 'by' => $check->checker?->name] : null,
                ])->values(),
                'history' => $application->history->map(fn ($h) => ['from' => $h->from_status?->label(), 'to' => $h->to_status->label(), 'by' => $h->actor->name, 'at' => $h->created_at->toIso8601String(), 'reason' => $h->reason])->values(),
            ],
            'checkStatuses' => array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], ScholarshipCheckStatus::cases()),
            'actions' => $editable ? array_values(array_map(fn ($s) => $s->value, array_filter($application->status->targets(), fn ($s) => Gate::allows('transition', [$application, $s])))) : [],
            'canEditChecks' => $editable && Gate::allows('review', $application),
            'canViewStudent' => Gate::allows('view', $application->student),
        ]);
    }

    public function transition(TransitionApplicationRequest $request, ScholarshipApplication $application, TransitionScholarshipApplication $action): RedirectResponse
    {
        $updated = $action->handle($request->user(), $application, ScholarshipApplicationStatus::from($request->validated('status')), $request->validated('reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dossier : '.$updated->status->label().'.']);

        return to_route('scholarships.applications.show', $application);
    }

    public function check(UpdateCheckRequest $request, ScholarshipApplication $application, UpdateScholarshipCheck $action): RedirectResponse
    {
        $action->handle($request->user(), $application, $request->validated('key'), ScholarshipCheckStatus::from($request->validated('status')), $request->validated('note'));

        return to_route('scholarships.applications.show', $application);
    }

    public function export(ApplicationListRequest $request): StreamedResponse
    {
        $filters = $request->safe()->only(ScholarshipApplicationFilters::KEYS);
        activity('scholarships')->causedBy($request->user())->event('applications_exported')
            ->withProperties(['filters' => array_keys(array_filter($filters, 'filled')), 'campaign_id' => $filters['campaign_id'] ?? null, 'status' => $filters['status'] ?? null])->log('applications_exported');

        return response()->streamDownload(function () use ($filters) {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                return;
            }
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['N° dossier', 'N° DDB', 'Matricule', 'Nom', 'Prénoms', 'Établissement', 'Niveau', 'Parcours', 'Campagne', 'Statut', 'Date de réception', 'Date de décision'], ';', '"', '');
            ScholarshipApplicationFilters::apply(ScholarshipApplication::query()->with(self::LIST_RELATIONS), $filters)->chunkById(500, function ($applications) use ($stream) {
                foreach ($applications as $a) {
                    $cells = [
                        $a->application_number, $a->ddb_number, $a->student->student_number, $a->student->last_name, $a->student->first_names, $a->studentRecord->establishment->name,
                        $a->studentRecord->level->name, $a->studentRecord->program?->name, $a->campaign->name, $a->status->label(), $a->received_at->format('Y-m-d H:i'), $a->decided_at?->format('Y-m-d H:i'),
                    ];
                    // Spreadsheet formula injection guard, as for import error exports.
                    fputcsv($stream, array_map(fn ($value) => preg_match('/^[=+@\-\t\r]/', (string) $value) ? "'".$value : $value, $cells), ';', '"', '');
                }
            });
            fclose($stream);
        }, 'dossiers-bourse-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
