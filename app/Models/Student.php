<?php

namespace App\Models;

use App\Support\StudentNormalization;
use Carbon\CarbonInterface;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property CarbonInterface|null $birth_date
 * @property CarbonInterface|null $cin_issued_at
 */
#[Fillable(['student_number', 'last_name', 'first_names', 'birth_date', 'birth_place', 'cin', 'cin_issued_at', 'cin_issued_place', 'phone', 'email', 'address', 'bac_registration_number', 'bac_series', 'bac_year', 'bac_mention', 'is_active'])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'cin_issued_at' => 'date', 'bac_year' => 'integer', 'is_active' => 'boolean'];
    }

    /**
     * Only the public uuid is generated; the integer id stays the primary key.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** URLs use the uuid, never the sequential id. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function setAttribute($key, $value): static
    {
        parent::setAttribute($key, StudentNormalization::value($key, $value));

        return $this;
    }

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new LogicException('Une identité étudiante doit être désactivée, pas supprimée.');
        });
    }

    /** @return HasMany<StudentRecord, $this> */
    public function studentRecords(): HasMany
    {
        return $this->hasMany(StudentRecord::class);
    }

    /**
     * @param  Builder<Student>  $query
     * @return Builder<Student>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when($user->hasRole('establishment'), fn ($query) => $query->whereHas('studentRecords', fn ($record) => $record->where('establishment_id', $user->establishment_id)));
    }

    /**
     * Students the establishment currently answers for: an active declaration in the current active year.
     *
     * @param  Builder<Student>  $query
     * @return Builder<Student>
     */
    public function scopeCurrentlyDeclaredBy(Builder $query, ?int $establishmentId): Builder
    {
        return $query->whereHas('studentRecords', fn ($record) => $record->currentActive()->where('establishment_id', $establishmentId));
    }

    /**
     * Server-side search shared by the student list and paper file reception:
     * identifier/contact prefixes and name substrings.
     *
     * @param  Builder<Student>  $query
     * @return Builder<Student>
     */
    public function scopeMatching(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search): void {
            foreach (['student_number', 'last_name', 'first_names', 'cin', 'bac_registration_number', 'phone', 'email'] as $field) {
                $term = (string) StudentNormalization::value($field, $search);
                $pattern = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
                $pattern = in_array($field, ['last_name', 'first_names'], true) ? '%'.$pattern.'%' : $pattern.'%';
                $q->orWhereRaw($field." LIKE ? ESCAPE '!'", [$pattern]);
            }
        });
    }

    /** @return HasMany<StudentInformationConfirmation, $this> */
    public function informationConfirmations(): HasMany
    {
        return $this->hasMany(StudentInformationConfirmation::class);
    }

    /** @return HasMany<ScholarshipApplication, $this> */
    public function scholarshipApplications(): HasMany
    {
        return $this->hasMany(ScholarshipApplication::class);
    }

    /** @return HasOne<User,$this> */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('students')->logOnly(['is_active'])->logOnlyDirty()->logEmptyChanges();
    }

    public function beforeActivityLogged(Model $activity, string $eventName): void
    {
        $fields = array_values(array_intersect(array_keys($eventName === 'created' ? $this->getAttributes() : $this->getChanges()), $this->getFillable()));
        $activity->setAttribute('properties', collect(['changed_fields' => $fields]));
    }
}
