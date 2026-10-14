<?php

use App\Models\Activity;
use App\Models\Establishment;
use App\Models\Mention;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const MENTION_HEADERS = ['Code établissement', 'Code mention', 'Nom de la mention'];
const PROGRAM_HEADERS = ['Code établissement', 'Code mention', 'Code parcours', 'Nom du parcours'];

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create()->assignRole('superadmin');
    $this->a = Establishment::factory()->create(['code' => 'FAC-A', 'name' => 'Faculté A']);
    $this->b = Establishment::factory()->create(['code' => 'FAC-B', 'name' => 'Faculté B']);
});

function csvFile(array $lines, string $separator = ';', bool $bom = true, string $name = 'import.csv'): UploadedFile
{
    $content = implode("\n", array_map(fn (array $cells) => implode($separator, $cells), $lines))."\n";

    return UploadedFile::fake()->createWithContent($name, ($bom ? "\xEF\xBB\xBF" : '').$content);
}

function xlsxFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows);
    $path = tempnam(sys_get_temp_dir(), 'ref').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'import.xlsx', null, null, true);
}

/** @return array<string, array<int, array<int, mixed>>> sheet title => rows */
function workbook(TestResponse $response): array
{
    $sheets = [];
    foreach (IOFactory::load($response->baseResponse->getFile()->getPathname())->getAllSheets() as $sheet) {
        $sheets[$sheet->getTitle()] = $sheet->toArray();
    }

    return $sheets;
}

function establishmentManager(Establishment $establishment): User
{
    $user = User::factory()->create();
    $user->forceFill(['establishment_id' => $establishment->id])->save();

    return $user->assignRole('establishment')->givePermissionTo('programs.manage');
}

/** Uploads the file and returns the preview url. */
function analyze(TestCase $test, string $kind, UploadedFile $file): string
{
    $response = $test->post('/references/'.$kind.'/import', ['file' => $file])->assertSessionHasNoErrors()->assertRedirect();

    return $response->headers->get('Location');
}

it('downloads a model with headers only and the codes of the actor perimeter', function () {
    Mention::factory()->create(['establishment_id' => $this->a->id, 'code' => 'INFO', 'name' => 'Informatique']);
    Mention::factory()->create(['establishment_id' => $this->b->id, 'code' => 'DROIT', 'name' => 'Droit']);
    $response = $this->actingAs($this->admin)->get('/references/mentions/template')->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('modele-mentions.xlsx');
    $sheets = workbook($response);
    expect(array_keys($sheets))->toBe(['Mentions', 'Codes'])->and($sheets['Mentions'])->toBe([MENTION_HEADERS])
        ->and(array_column($sheets['Codes'], 0))->toBe(['Code établissement', 'FAC-A', 'FAC-B']);

    $this->actingAs(establishmentManager($this->a));
    $sheets = workbook($this->get('/references/programs/template')->assertOk());
    expect($sheets['Parcours'])->toBe([PROGRAM_HEADERS])->and(array_slice($sheets['Codes'], 1))->toBe([['FAC-A', 'Faculté A', 'INFO', 'Informatique']]);
});

it('exports the filtered list within the perimeter with the model columns', function () {
    $mention = Mention::factory()->create(['establishment_id' => $this->a->id, 'code' => 'INFO', 'name' => 'Informatique']);
    Program::factory()->create(['establishment_id' => $this->a->id, 'mention_id' => $mention->id, 'code' => 'GL', 'name' => 'Génie logiciel']);
    Program::factory()->create(['establishment_id' => $this->a->id, 'code' => null, 'name' => 'Réseaux']);
    Program::factory()->create(['establishment_id' => $this->b->id, 'code' => 'GL', 'name' => 'Gestion']);

    $sheets = workbook($this->actingAs($this->admin)->get('/references/programs/export?establishment_id='.$this->a->id.'&search=Génie')->assertOk());
    expect($sheets['Parcours'])->toBe([PROGRAM_HEADERS, ['FAC-A', 'INFO', 'GL', 'Génie logiciel']]);
    expect(Activity::where('event', 'references_exported')->sole()->properties->all())->toEqual(['kind' => 'programs', 'filters' => ['search', 'establishment_id'], 'total_rows' => 1]);

    $sheets = workbook($this->actingAs(establishmentManager($this->b))->get('/references/programs/export')->assertOk());
    expect(array_slice($sheets['Parcours'], 1))->toBe([['FAC-B', null, 'GL', 'Gestion']]);
});

it('classifies every imported mention row with a french reason', function () {
    Mention::factory()->create(['establishment_id' => $this->a->id, 'code' => 'INFO', 'name' => 'Informatique']);
    Mention::factory()->create(['establishment_id' => $this->a->id, 'code' => 'PHYS', 'name' => 'Physique']);
    Mention::factory()->create(['establishment_id' => $this->a->id, 'code' => null, 'name' => 'Droit']);
    $this->actingAs($this->admin);
    $preview = analyze($this, 'mentions', csvFile([MENTION_HEADERS,
        ['FAC-A', 'INFO', 'Informatique'],           // 2 unchanged
        ['fac-a', ' math ', 'Mathématiques'],         // 3 create (normalized)
        ['FAC-A', 'phys', 'Physique appliquée'],     // 4 update
        ['FAC-X', 'X1', 'Inconnu'],                  // 5 unknown establishment
        ['FAC-A', 'BAD CODE', 'Nom'],                // 6 bad code
        ['FAC-A', 'DUP', 'Un'],                      // 7 duplicate in file
        ['FAC-A', 'DUP', 'Deux'],                    // 8 duplicate in file
        ['FAC-A', '', 'Droit'],                      // 9 identical without code
        ['FAC-A', '', 'informatique'],               // 10 probable duplicate
        ['FAC-A', 'CHIM', ''],                       // 11 missing name
        ['', '', ''],                                // skipped empty line
    ]));
    $this->get($preview)->assertOk()->assertInertia(fn (Assert $page) => $page->component('references/import-preview')
        ->where('counts', ['create' => 1, 'update' => 1, 'unchanged' => 2, 'error' => 6, 'total' => 10])->where('canConfirm', true)
        ->where('rows.0.status', 'unchanged')->where('rows.1.status', 'create')->where('rows.1.code', 'MATH')->where('rows.1.establishment_code', 'FAC-A')
        ->where('rows.2.status', 'update')->where('rows.2.reason', 'Nom modifié.')
        ->where('rows.3.reason', 'Code établissement inconnu.')
        ->where('rows.4.reason', 'Code invalide : lettres, chiffres, points, tirets ou underscores uniquement.')
        ->where('rows.5.reason', 'Code présent plusieurs fois dans le fichier (lignes 7, 8).')->where('rows.6.status', 'error')
        ->where('rows.7.status', 'unchanged')->where('rows.8.status', 'error')->where('rows.8.line', 10)
        ->where('rows.9.reason', 'Nom manquant.'));
    expect(Mention::count())->toBe(3);
});

it('imports programs only in the perimeter of an establishment user', function () {
    $own = Mention::factory()->create(['establishment_id' => $this->a->id, 'code' => 'INFO']);
    Mention::factory()->create(['establishment_id' => $this->b->id, 'code' => 'ECO']);
    $program = Program::factory()->create(['establishment_id' => $this->a->id, 'code' => 'GL', 'name' => 'Génie logiciel']);
    $this->actingAs(establishmentManager($this->a));
    $preview = analyze($this, 'programs', csvFile([PROGRAM_HEADERS,
        ['FAC-A', 'INFO', 'GL', 'Génie logiciel'],
        ['FAC-A', 'ECO', 'RES', 'Réseaux'],
        ['FAC-B', '', 'FIN', 'Finance'],
        ['FAC-A', '', '', 'Sans code'],
    ], ',', false));
    $this->get($preview)->assertInertia(fn (Assert $page) => $page
        ->where('rows.0.status', 'update')->where('rows.0.reason', 'Mention modifiée.')
        ->where('rows.1.reason', 'Code mention inconnu pour cet établissement.')
        ->where('rows.2.reason', 'Établissement hors de votre périmètre.')
        ->where('rows.3.status', 'create'));

    $this->post($preview)->assertRedirect('/references/programs');
    expect($program->fresh()->mention_id)->toBe($own->id)
        ->and(Program::where('name', 'Sans code')->sole()->only(['establishment_id', 'code', 'mention_id']))->toBe(['establishment_id' => $this->a->id, 'code' => null, 'mention_id' => null])
        ->and(Program::where('establishment_id', $this->b->id)->exists())->toBeFalse();
});

it('confirms in one transaction, skips error rows and audits counts only', function () {
    $mention = Mention::factory()->create(['establishment_id' => $this->a->id, 'code' => 'PHYS', 'name' => 'Physique']);
    $this->actingAs($this->admin);
    $preview = analyze($this, 'mentions', xlsxFile([MENTION_HEADERS,
        ['FAC-A', 'MATH', 'Mathématiques'], ['FAC-B', 'MATH', 'Maths B'], ['FAC-A', 'PHYS', 'Physique appliquée'], ['FAC-Z', 'X', 'Erreur'],
    ]));
    $this->post($preview)->assertRedirect('/references/mentions');
    expect(session(SessionKey::FLASH_DATA)['toast']['message'])->toBe('Import terminé : 2 créé(s), 1 mis à jour, 1 ignoré(s).')
        ->and($mention->fresh()->name)->toBe('Physique appliquée')
        ->and(Mention::where('code', 'MATH')->pluck('establishment_id')->sort()->values()->all())->toBe([$this->a->id, $this->b->id])
        ->and(Mention::where('name', 'Erreur')->exists())->toBeFalse();
    $audit = Activity::where('event', 'references_imported')->sole();
    expect($audit->log_name)->toBe('references')->and($audit->causer_id)->toBe($this->admin->id)
        ->and($audit->properties->all())->toEqual(['kind' => 'mentions', 'total_rows' => 4, 'created_rows' => 2, 'updated_rows' => 1, 'skipped_rows' => 1])
        ->and(Activity::where('log_name', 'references')->whereIn('event', ['created', 'updated'])->where('causer_id', $this->admin->id)->count())->toBe(3);
    $this->get($preview)->assertNotFound();
});

it('rolls back the whole import and its audit when a write fails', function () {
    $this->actingAs($this->admin);
    $preview = analyze($this, 'mentions', csvFile([MENTION_HEADERS, ['FAC-A', 'M1', 'Un'], ['FAC-A', 'M2', 'Deux']]));
    Mention::creating(function (Mention $mention): void {
        if ($mention->code === 'M2') {
            throw new RuntimeException('Simulated failure');
        }
    });
    $audits = Activity::count();
    $this->withoutExceptionHandling();
    expect(fn () => $this->post($preview))->toThrow(RuntimeException::class);
    expect(Mention::count())->toBe(0)->and(Activity::count())->toBe($audits);
});

it('refuses a stale preview and shows the new analysis', function () {
    $this->actingAs($this->admin);
    $preview = analyze($this, 'mentions', csvFile([MENTION_HEADERS, ['FAC-A', 'M1', 'Un']]));
    Mention::factory()->create(['establishment_id' => $this->a->id, 'code' => 'M1', 'name' => 'Autre']);
    $this->post($preview)->assertRedirect($preview);
    expect(Mention::count())->toBe(1);
    $this->get($preview)->assertInertia(fn (Assert $page) => $page->where('rows.0.status', 'update'));
});

it('re-imports an unchanged export without any change', function () {
    Mention::factory()->create(['establishment_id' => $this->a->id, 'code' => 'INFO', 'name' => '=Informatique']);
    Mention::factory()->create(['establishment_id' => $this->a->id, 'code' => '007', 'name' => 'Codes à zéros']);
    $export = $this->actingAs($this->admin)->get('/references/mentions/export')->assertOk();
    $file = new UploadedFile($export->baseResponse->getFile()->getPathname(), 'export.xlsx', null, null, true);
    $this->get(analyze($this, 'mentions', $file))->assertInertia(fn (Assert $page) => $page
        ->where('counts.unchanged', 2)->where('counts.total', 2)->where('canConfirm', false));
});

it('binds the import token to its user and kind', function () {
    $this->actingAs($this->admin);
    $preview = analyze($this, 'mentions', csvFile([MENTION_HEADERS, ['FAC-A', 'M1', 'Un']]));
    $this->get(str_replace('/mentions/', '/programs/', $preview))->assertNotFound();
    $this->actingAs(User::factory()->create()->assignRole('superadmin'));
    $this->get($preview)->assertNotFound();
    $this->post($preview)->assertNotFound();
    expect(Mention::count())->toBe(0);
});

it('denies the transfer routes without programs.manage', function (string $kind) {
    $this->actingAs(User::factory()->create()->assignRole('central_scholarship_office'));
    $this->get('/references/'.$kind.'/template')->assertForbidden();
    $this->get('/references/'.$kind.'/export')->assertForbidden();
    $this->post('/references/'.$kind.'/import', ['file' => csvFile([MENTION_HEADERS])])->assertForbidden();
    $this->get('/references/'.$kind.'/import/'.str_repeat('a', 40))->assertForbidden();
    $this->actingAs($this->admin)->get('/references/levels/template')->assertMethodNotAllowed();
    $this->get('/references/mentions/import/short-token')->assertNotFound();
})->with(['mentions', 'programs']);

it('rejects invalid files before any preview', function (Closure $file, string $message) {
    $this->actingAs($this->admin)->post('/references/mentions/import', ['file' => $file()])
        ->assertSessionHasErrors(['file' => $message]);
})->with([
    'extension' => [fn () => UploadedFile::fake()->createWithContent('import.txt', "a;b\n"), 'Seules les extensions .xlsx et .csv sont acceptées.'],
    'size' => [fn () => UploadedFile::fake()->create('import.csv', 2049, 'text/csv'), 'Le fichier dépasse la taille maximale autorisée (2 Mo).'],
    'headers' => [fn () => csvFile([['Code', 'Nom'], ['FAC-A', 'X']]), 'Colonnes manquantes : Code établissement, Code mention, Nom de la mention. Téléchargez le modèle pour obtenir les en-têtes attendus.'],
    'empty' => [fn () => csvFile([MENTION_HEADERS]), 'Le fichier ne contient aucune ligne de données.'],
    'rows' => [fn () => csvFile([MENTION_HEADERS, ...array_map(fn (int $i) => ['FAC-A', 'M'.$i, 'Nom '.$i], range(1, 1001))]), 'Le fichier dépasse 1000 lignes de données.'],
    'encoding' => [fn () => csvFile([MENTION_HEADERS, ['FAC-A', 'M1', "Droit \xE9conomique"]], ';', false), 'Le fichier CSV doit être encodé en UTF-8.'],
]);
