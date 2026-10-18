<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Scholarship campaigns are removed: the national scholarship is the only one, and a paper file
 * belongs to an academic year (one request per student and per year, ADR-085). Every file keeps
 * its year (taken from its campaign), its DDB number and its technical number; the numbering
 * sequence moves to the academic year. Stops before any change if a year holds two files of the
 * same student or the same DDB number.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['campaigns.view', 'campaigns.create', 'campaigns.update', 'campaigns.open', 'campaigns.close', 'campaigns.manage'];

    public function up(): void
    {
        $this->ensureNoConflict();

        Schema::table('academic_years', fn (Blueprint $table) => $table->unsignedInteger('last_application_sequence')->default(0));
        Schema::table('scholarship_applications', fn (Blueprint $table) => $table->foreignId('academic_year_id')->nullable()->after('application_number')->constrained()->restrictOnDelete());
        DB::table('scholarship_applications')->update(['academic_year_id' => DB::raw('(SELECT academic_year_id FROM scholarship_campaigns WHERE scholarship_campaigns.id = scholarship_applications.scholarship_campaign_id)')]);

        Schema::table('scholarship_applications', function (Blueprint $table) {
            $table->dropForeign(['scholarship_campaign_id']);
            $table->dropUnique('scholarship_applications_campaign_student_unique');
            $table->dropUnique('scholarship_applications_campaign_sequence_unique');
            $table->dropUnique('scholarship_applications_campaign_ddb_unique');
            $table->dropIndex('scholarship_applications_campaign_status_index');
        });

        // Keep existing sequences and high-water marks, including numbers of purged files.
        // When several campaigns share a year, allocate a disjoint range to each campaign.
        foreach (DB::table('scholarship_campaigns')->orderBy('academic_year_id')->orderBy('id')->get() as $campaign) {
            $offset = (int) DB::table('academic_years')->where('id', $campaign->academic_year_id)->value('last_application_sequence');
            $files = DB::table('scholarship_applications')->where('scholarship_campaign_id', $campaign->id);
            $maximum = max((int) $campaign->last_application_sequence, (int) $files->max('sequence'));
            $files->increment('sequence', $offset);
            DB::table('academic_years')->where('id', $campaign->academic_year_id)->update(['last_application_sequence' => $offset + $maximum]);
        }
        Schema::table('scholarship_applications', fn (Blueprint $table) => $table->dropColumn('scholarship_campaign_id'));

        Schema::table('scholarship_applications', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable(false)->change();
            $table->unique(['academic_year_id', 'sequence'], 'scholarship_applications_year_sequence_unique');
            // NULL keys are allowed many times: only a provided DDB number must be unique in its year.
            $table->unique(['academic_year_id', 'ddb_key'], 'scholarship_applications_year_ddb_unique');
            $table->index(['academic_year_id', 'status', 'received_at'], 'scholarship_applications_year_status_index');
            $table->unsignedTinyInteger('live_marker')->nullable()->virtualAs('CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END');
            $table->unique(['academic_year_id', 'student_id', 'live_marker'], 'scholarship_applications_year_student_unique');
        });

        // The DDB pattern loses the campaign token.
        $pattern = DB::table('platform_settings')->where('key', 'ddb_pattern')->value('value');
        if (is_string($pattern) && str_contains($pattern, '{CAMPAGNE}')) {
            DB::table('platform_settings')->where('key', 'ddb_pattern')->update(['value' => str_replace('{CAMPAGNE}', '', $pattern), 'updated_at' => now()]);
        }

        Cache::forget('platform.ddb');

        $tables = config('permission.table_names');
        $ids = DB::table($tables['permissions'])->whereIn('name', self::PERMISSIONS)->pluck('id');
        DB::table($tables['role_has_permissions'])->whereIn('permission_id', $ids)->delete();
        DB::table($tables['model_has_permissions'])->whereIn('permission_id', $ids)->delete();
        DB::table($tables['permissions'])->whereIn('id', $ids)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::dropIfExists('scholarship_campaigns');
    }

    /** One « Bourse nationale » campaign per year holding files, opened for the current year. */
    public function down(): void
    {
        if (DB::table('scholarship_applications')->select('academic_year_id', 'student_id')->groupBy('academic_year_id', 'student_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Retour aux campagnes impossible : plusieurs dossiers du même étudiant dans une année, corbeille comprise. Résolvez ces doublons avant le rollback.');
        }

        Schema::create('scholarship_campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('status', 20)->default('draft')->index();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->unsignedInteger('last_application_sequence')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes()->index();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('trash_batch')->nullable()->index();
            $table->unique(['academic_year_id', 'name'], 'scholarship_campaigns_year_name_unique');
        });
        Schema::table('scholarship_applications', fn (Blueprint $table) => $table->foreignId('scholarship_campaign_id')->nullable()->after('application_number')->constrained()->restrictOnDelete());
        foreach (DB::table('academic_years')->get(['id', 'is_current', 'last_application_sequence']) as $year) {
            if (! DB::table('scholarship_applications')->where('academic_year_id', $year->id)->exists() && ! $year->is_current) {
                continue;
            }
            $campaign = DB::table('scholarship_campaigns')->insertGetId(['uuid' => (string) Str::uuid7(), 'academic_year_id' => $year->id, 'name' => 'Bourse nationale', 'status' => $year->is_current ? 'open' : 'closed', 'last_application_sequence' => $year->last_application_sequence, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('scholarship_applications')->where('academic_year_id', $year->id)->update(['scholarship_campaign_id' => $campaign]);
        }
        Schema::table('scholarship_applications', function (Blueprint $table) {
            // MySQL may use any year-prefixed index to support this foreign key.
            $table->dropForeign(['academic_year_id']);
        });
        Schema::table('scholarship_applications', function (Blueprint $table) {
            $table->dropUnique('scholarship_applications_year_sequence_unique');
            $table->dropUnique('scholarship_applications_year_ddb_unique');
            $table->dropIndex('scholarship_applications_year_status_index');
            $table->dropUnique('scholarship_applications_year_student_unique');
            $table->dropColumn('live_marker');
        });
        Schema::table('scholarship_applications', function (Blueprint $table) {
            $table->dropColumn('academic_year_id');
            $table->unique(['scholarship_campaign_id', 'student_id'], 'scholarship_applications_campaign_student_unique');
            $table->unique(['scholarship_campaign_id', 'sequence'], 'scholarship_applications_campaign_sequence_unique');
            $table->unique(['scholarship_campaign_id', 'ddb_key'], 'scholarship_applications_campaign_ddb_unique');
            $table->index(['scholarship_campaign_id', 'status', 'received_at'], 'scholarship_applications_campaign_status_index');
        });
        Schema::table('academic_years', fn (Blueprint $table) => $table->dropColumn('last_application_sequence'));
    }

    /** Two files of one student, or one DDB number twice, in the same year: to resolve by hand first. */
    private function ensureNoConflict(): void
    {
        $byYear = fn () => DB::table('scholarship_applications')->join('scholarship_campaigns', 'scholarship_campaigns.id', '=', 'scholarship_applications.scholarship_campaign_id');
        $studentsQuery = $byYear()->whereNull('scholarship_applications.deleted_at')->select('scholarship_applications.student_id', 'scholarship_campaigns.academic_year_id')
            ->groupBy('scholarship_applications.student_id', 'scholarship_campaigns.academic_year_id')->havingRaw('COUNT(*) > 1');
        $ddbQuery = $byYear()->whereNotNull('scholarship_applications.ddb_key')->select('scholarship_applications.ddb_key', 'scholarship_campaigns.academic_year_id')
            ->groupBy('scholarship_applications.ddb_key', 'scholarship_campaigns.academic_year_id')->havingRaw('COUNT(*) > 1');
        $students = DB::query()->fromSub($studentsQuery, 'student_conflicts')->count();
        $ddb = DB::query()->fromSub($ddbQuery, 'ddb_conflicts')->count();
        if ($students > 0 || $ddb > 0) {
            throw new RuntimeException("Suppression des campagnes impossible : {$students} étudiant(s) avec plusieurs dossiers dans une même année et {$ddb} N° DDB en double dans une même année. Résolvez les dossiers actifs en double et les N° DDB en conflit (corbeille comprise), puis relancez la migration.");
        }
    }
};
