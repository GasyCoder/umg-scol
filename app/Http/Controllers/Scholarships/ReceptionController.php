<?php

namespace App\Http\Controllers\Scholarships;

use App\Actions\Scholarships\ReceiveScholarshipApplication;
use App\Enums\ScholarshipCampaignStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scholarships\ReceiveApplicationRequest;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipCampaign;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReceptionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('receive', ScholarshipApplication::class);
        $filters = $request->validate(['campaign_id' => ['nullable', 'integer'], 'search' => ['nullable', 'string', 'max:100']], ['max' => 'La recherche doit contenir au plus :max caractères.']);
        $campaigns = ScholarshipCampaign::query()->with('academicYear:id,name')->where('status', ScholarshipCampaignStatus::Open)->orderBy('name')->get();
        $campaign = $campaigns->firstWhere('id', (int) ($filters['campaign_id'] ?? 0)) ?? ($campaigns->count() === 1 ? $campaigns->first() : null);
        $search = trim((string) ($filters['search'] ?? ''));
        $results = [];
        if ($campaign !== null && mb_strlen($search) >= 2) {
            // Server-side search, capped: the secretariat refines the query instead of browsing the base.
            $results = Student::query()->select(['id', 'student_number', 'last_name', 'first_names', 'birth_date', 'is_active'])->matching($search)
                ->with([
                    'studentRecords' => fn ($q) => $q->where('academic_year_id', $campaign->academic_year_id)->with(['establishment:id,name', 'level:id,name', 'program:id,name']),
                    'scholarshipApplications' => fn ($q) => $q->where('scholarship_campaign_id', $campaign->id)->select(['id', 'student_id', 'application_number']),
                ])->orderBy('last_name')->orderBy('first_names')->limit(20)->get()
                ->map(fn (Student $student) => [
                    'id' => $student->id, 'student_number' => $student->student_number, 'last_name' => $student->last_name, 'first_names' => $student->first_names,
                    'birth_date' => $student->birth_date?->format('Y-m-d'), 'is_active' => $student->is_active,
                    'records' => $student->studentRecords->map(fn ($record) => ['establishment' => $record->establishment->name, 'level' => $record->level->name, 'program' => $record->program?->name, 'status' => $record->status->value])->values(),
                    'application' => $student->scholarshipApplications->first()?->only(['id', 'application_number']),
                ]);
        }

        return Inertia::render('scholarships/reception', [
            'campaigns' => $campaigns->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'academic_year' => $c->academicYear->name]),
            'campaignId' => $campaign?->id, 'search' => $search, 'results' => $results,
        ]);
    }

    public function store(ReceiveApplicationRequest $request, ReceiveScholarshipApplication $action): RedirectResponse
    {
        $application = $action->handle($request->user(), $request->integer('campaign_id'), $request->integer('student_id'), $request->validated('ddb_number'), $request->validated('internal_notes'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Réception enregistrée : dossier n° '.$application->application_number.'.']);

        return to_route('scholarships.applications.show', $application);
    }
}
