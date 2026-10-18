<?php

use App\Models\Domain;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Mention;
use App\Models\Program;
use Database\Seeders\UmgReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds the UMG levels, domains, establishments, mentions and programs once', function () {
    $this->seed(UmgReferenceSeeder::class);
    $counts = [Level::count(), Domain::count(), Establishment::count(), Mention::count(), Program::count()];
    expect(Level::query()->orderBy('sort_order')->pluck('code')->all())->toBe(['L1', 'L2', 'L3', 'M1', 'M2', 'D1', 'D2', 'D3'])
        ->and(Establishment::query()->count())->toBe(22)
        ->and(Establishment::query()->where('code', 'FACMED')->sole()->name)->toBe('Faculté de Médecine')
        ->and(Mention::query()->whereHas('establishment', fn ($q) => $q->where('code', 'EDSPM'))->where('name', 'Droit')->sole()->domain->code)->toBe('ALSH')
        ->and(Program::query()->whereHas('mention', fn ($q) => $q->where('name', 'Finance'))->sole()->name)->toBe('Finance');

    $this->seed(UmgReferenceSeeder::class);
    expect([Level::count(), Domain::count(), Establishment::count(), Mention::count(), Program::count()])->toBe($counts);
});

it('keeps what the Scolarité edited or deleted and reuses an establishment known under another code', function () {
    $edsp = Establishment::factory()->create(['code' => 'EDSP', 'name' => 'Ecole de Droit et Science Politique (Antsohihy)']);
    $facmed = Establishment::factory()->create(['code' => 'FACMED', 'name' => 'Faculté de Médecine de Mahajanga']);
    Mention::factory()->create(['establishment_id' => $facmed->id, 'name' => 'Médecine humaine', 'deleted_at' => now()]);
    $this->seed(UmgReferenceSeeder::class);
    expect(Establishment::query()->where('code', 'EDSPA')->exists())->toBeFalse()
        ->and($edsp->mentions()->where('name', 'Droit')->exists())->toBeTrue()
        ->and($facmed->refresh()->name)->toBe('Faculté de Médecine de Mahajanga')
        ->and(Mention::query()->where('establishment_id', $facmed->id)->count())->toBe(0);
});
