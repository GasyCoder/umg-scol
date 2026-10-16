<?php

namespace App\Support\Trash;

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
use Illuminate\Database\Eloquent\Model;

class TrashCatalogue
{
    /** @var array<string,class-string<Model>> */
    public const MODELS = ['establishments' => Establishment::class, 'academic-years' => AcademicYear::class, 'levels' => Level::class, 'mentions' => Mention::class, 'programs' => Program::class, 'campaigns' => ScholarshipCampaign::class, 'imports' => StudentImport::class, 'students' => Student::class, 'student-records' => StudentRecord::class, 'applications' => ScholarshipApplication::class];

    public const LABELS = ['establishments' => 'Établissements', 'academic-years' => 'Années universitaires', 'levels' => 'Niveaux', 'mentions' => 'Mentions', 'programs' => 'Parcours', 'campaigns' => 'Campagnes', 'imports' => 'Imports', 'students' => 'Étudiants', 'student-records' => 'Situations annuelles', 'applications' => 'Dossiers de bourse'];

    public static function model(string $kind): Model
    {
        abort_unless(isset(self::MODELS[$kind]), 404);
        $class = self::MODELS[$kind];

        return new $class;
    }

    public static function label(Model $model): string
    {
        return (string) ($model->getAttribute('name') ?? $model->getAttribute('original_filename') ?? $model->getAttribute('application_number') ?? $model->getAttribute('last_name') ?? 'Situation annuelle #'.$model->getKey());
    }
}
