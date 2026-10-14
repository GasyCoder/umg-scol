<?php

namespace App\Http\Controllers\Scholarships;

use App\Actions\Scholarships\SaveScholarshipCampaign;
use App\Actions\Scholarships\TransitionScholarshipCampaign;
use App\Enums\ScholarshipCampaignStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scholarships\SaveCampaignRequest;
use App\Models\AcademicYear;
use App\Models\ScholarshipCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ScholarshipCampaign::class);
        $canManage = Gate::allows('manage', ScholarshipCampaign::class);

        return Inertia::render('scholarships/campaigns', [
            'campaigns' => ScholarshipCampaign::query()->with('academicYear:id,name')->withCount('applications')->orderByDesc('id')->paginate(20)->through(fn (ScholarshipCampaign $campaign) => [
                'id' => $campaign->id, 'uuid' => $campaign->uuid, 'name' => $campaign->name, 'academic_year_id' => $campaign->academic_year_id, 'academic_year' => $campaign->academicYear->name,
                'starts_at' => $campaign->starts_at?->format('Y-m-d'), 'ends_at' => $campaign->ends_at?->format('Y-m-d'), 'notes' => $campaign->notes,
                'status' => $campaign->status->value, 'status_label' => $campaign->status->label(), 'applications_count' => $campaign->applications_count,
                'targets' => $canManage ? array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], $campaign->status->targets()) : [],
            ]),
            'academicYears' => $canManage ? AcademicYear::query()->where('is_active', true)->orderByDesc('name')->get(['id', 'name']) : [],
            'canManage' => $canManage,
        ]);
    }

    public function store(SaveCampaignRequest $request, SaveScholarshipCampaign $action): RedirectResponse
    {
        $action->handle($request->user(), null, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Campagne créée en brouillon.']);

        return to_route('scholarships.campaigns.index');
    }

    public function update(SaveCampaignRequest $request, ScholarshipCampaign $campaign, SaveScholarshipCampaign $action): RedirectResponse
    {
        $action->handle($request->user(), $campaign, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Campagne mise à jour.']);

        return to_route('scholarships.campaigns.index');
    }

    public function transition(Request $request, ScholarshipCampaign $campaign, TransitionScholarshipCampaign $action): RedirectResponse
    {
        Gate::authorize('manage', ScholarshipCampaign::class);
        $data = $request->validate(['status' => ['required', Rule::enum(ScholarshipCampaignStatus::class)]], ['status' => 'Statut invalide.']);
        $updated = $action->handle($request->user(), $campaign, ScholarshipCampaignStatus::from($data['status']));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Campagne : '.$updated->status->label().'.']);

        return to_route('scholarships.campaigns.index');
    }
}
