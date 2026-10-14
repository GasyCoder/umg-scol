<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\AuditFilterRequest;
use App\Models\User;
use App\Support\Audit\AuditPresenter;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditController extends Controller
{
    public function index(AuditFilterRequest $request): Response
    {
        $filters = $request->safe()->only(AuditFilterRequest::KEYS);

        return Inertia::render('audits/index', [
            'activities' => $this->query($filters)->with(['causer' => fn (MorphTo $morph) => $morph->morphWith([User::class => ['roles']])])
                ->latest('id')->paginate(25)->withQueryString()->through(fn (Activity $activity) => AuditPresenter::summary($activity)),
            'filters' => $filters,
            'options' => [
                // Internal accounts only: student accounts stay anonymous in the audit UI.
                'actors' => User::query()->role(['superadmin', 'developer', 'central_scholarship_office', 'establishment'])->orderBy('name')->get(['id', 'name']),
                'modules' => Activity::query()->whereNotNull('log_name')->distinct()->orderBy('log_name')->pluck('log_name')->map(fn ($module) => ['value' => $module, 'label' => AuditPresenter::MODULES[$module] ?? $module])->values(),
                'events' => Activity::query()->whereNotNull('event')->distinct()->orderBy('event')->pluck('event')->map(fn ($event) => ['value' => $event, 'label' => AuditPresenter::EVENTS[$event] ?? $event])->values(),
                'subjects' => Activity::query()->whereNotNull('subject_type')->distinct()->pluck('subject_type')->map(fn ($type) => class_basename($type))->unique()->sort()->values(),
            ],
            'canExport' => Gate::allows('export', Activity::class),
        ]);
    }

    public function show(Activity $activity): Response
    {
        Gate::authorize('view', $activity);
        $activity->load(['causer' => fn (MorphTo $morph) => $morph->morphWith([User::class => ['roles']])]);

        return Inertia::render('audits/show', ['activity' => AuditPresenter::detail($activity)]);
    }

    public function export(AuditFilterRequest $request): StreamedResponse
    {
        $filters = $request->safe()->only(AuditFilterRequest::KEYS);
        activity('audits')->causedBy($request->user())->event('audits_exported')->withProperties(['filters' => array_keys(array_filter($filters, 'filled'))])->log('audits_exported');

        return CsvExport::download('audit-'.now()->format('Ymd-His').'.csv', ['Date', 'Acteur', 'Rôle', 'Module', 'Événement', 'Type de sujet', 'ID sujet', 'Métadonnées administratives'], function (callable $write) use ($filters) {
            $this->query($filters)->with(['causer' => fn (MorphTo $morph) => $morph->morphWith([User::class => ['roles']])])->chunkById(500, function ($activities) use ($write) {
                foreach ($activities as $activity) {
                    $row = AuditPresenter::summary($activity);
                    $meta = collect(AuditPresenter::metadata($activity->properties?->all() ?? []))->map(fn ($item) => $item['key'].'='.$item['value'])->implode(' ; ');
                    $write([$activity->created_at?->format('Y-m-d H:i:s'), $row['actor'], $row['role'], $row['module_label'], $row['event_label'], $row['subject_type'], $row['subject_id'], $meta]);
                }
            });
        });
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return Builder<Activity>
     */
    private function query(array $filters): Builder
    {
        return Activity::query()
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<', Carbon::parse($to)->addDay()->startOfDay()))
            ->when($filters['actor'] ?? null, fn ($q, $actor) => $q->where('causer_type', (new User)->getMorphClass())->where('causer_id', $actor))
            ->when($filters['module'] ?? null, fn ($q, $module) => $q->where('log_name', $module))
            ->when($filters['event'] ?? null, fn ($q, $event) => $q->where('event', $event))
            ->when($filters['subject'] ?? null, fn ($q, $subject) => $q->where(fn ($inner) => $inner->where('subject_type', $subject)->orWhere('subject_type', 'like', '%\\'.$subject)));
    }
}
