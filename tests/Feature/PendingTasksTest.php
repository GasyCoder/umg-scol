<?php

use App\Actions\Corrections\RequestStudentCorrection;
use App\Enums\StudentCorrectionField;
use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\PendingTasks;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->a = Establishment::factory()->create();
    $this->b = Establishment::factory()->create();
    $year = AcademicYear::factory()->create(['is_current' => true, 'is_active' => true]);
    $student = Student::factory()->create(['phone' => '0341234567']);
    StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $year->id, 'establishment_id' => $this->a->id, 'status' => 'active']);
    $studentUser = User::factory()->create()->assignRole('student');
    $studentUser->forceFill(['student_id' => $student->id])->save();
    app(RequestStudentCorrection::class)->handle($studentUser, StudentCorrectionField::Phone, '0381234567', null);
    $this->staffA = User::factory()->create()->assignRole('establishment');
    $this->staffA->forceFill(['establishment_id' => $this->a->id])->save();
    $this->staffB = User::factory()->create()->assignRole('establishment');
    $this->staffB->forceFill(['establishment_id' => $this->b->id])->save();
    $this->admin = User::factory()->create()->assignRole('superadmin');
});

it('counts pending work within the actor perimeter only', function () {
    expect(PendingTasks::forUser($this->admin)['items'])->toContain(['key' => 'corrections', 'label' => 'Corrections à examiner', 'description' => 'Demandes de correction signalées par des étudiants.', 'href' => '/corrections?status=pending', 'count' => 1]);
    expect(collect(PendingTasks::forUser($this->staffA)['items'])->firstWhere('key', 'corrections')['count'] ?? 0)->toBe(1);
    expect(PendingTasks::forUser($this->staffB))->toBe(['total' => 0, 'items' => []]);
});

it('hides empty tasks and tasks the actor may not handle', function () {
    $student = User::factory()->create()->assignRole('student');
    expect(PendingTasks::forUser($student))->toBe(['total' => 0, 'items' => []]);
    expect(collect(PendingTasks::forUser($this->admin)['items'])->pluck('count')->every(fn ($count) => $count > 0))->toBeTrue();
});

it('shares the tasks with every internal page', function () {
    $this->actingAs($this->staffA)->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('tasks.total', 1)->where('tasks.items.0.key', 'corrections'));
    $this->actingAs($this->staffB)->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('tasks.total', 0)->where('tasks.items', []));
});
