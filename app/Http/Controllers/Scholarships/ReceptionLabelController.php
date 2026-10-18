<?php

namespace App\Http\Controllers\Scholarships;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ScholarshipApplication;
use App\Support\Branding;
use App\Support\Scholarships\DdbLabel;
use App\Support\Scholarships\DdbNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sheet of blank DDB labels (10 per A4 page): the prefix of the platform pattern is printed,
 * the agent writes the number by hand. No personal data, nothing stored.
 */
class ReceptionLabelController extends Controller
{
    public function show(Request $request): Response
    {
        Gate::authorize('printLabels', ScholarshipApplication::class);
        $filters = $request->validate(['academic_year_id' => ['nullable', 'integer']]);
        // Active years, the current one first: labels may be prepared for the next year.
        $years = AcademicYear::query()->where('is_active', true)->orderByDesc('is_current')->orderByDesc('starts_at')->orderByDesc('id')->get(['id', 'name', 'is_current']);
        $year = $years->firstWhere('id', (int) ($filters['academic_year_id'] ?? 0)) ?? $years->first();
        $texts = DdbLabel::texts();

        return Inertia::render('scholarships/reception-labels', [
            'years' => $years->map(fn (AcademicYear $y) => ['id' => $y->id, 'name' => $y->name, 'is_current' => $y->is_current])->values(),
            'yearId' => $year?->id,
            'label' => $year !== null ? DdbNumber::labelParts($year) : null,
            'institution' => Branding::get()['institution_name'],
            // Texts set in Paramètres › Plateforme; the QR code is rendered once, as an image.
            'texts' => [...$texts, 'urlLabel' => DdbLabel::urlLabel($texts['url'])],
            'qr' => DdbLabel::qr($texts['url']),
            'maxSheets' => (int) config('scholarships.label_sheets_max'),
        ]);
    }
}
