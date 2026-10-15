<?php

namespace App\Support\Audit;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * The only way audit entries leave the database (pages, dashboard feed, CSV).
 * Properties are read through a WHITELIST: administrative identifiers, statuses, counters and field
 * names. Anything else (personal data, secrets, proposed values, free text) is never shown, even if a
 * future log line stored it by mistake. Values of changed attributes are shown only for safe keys.
 */
final class AuditPresenter
{
    /** Keys whose scalar values are administrative and safe to display. */
    public const SAFE_KEYS = [
        'user_id', 'student_id', 'student_record_id', 'academic_year_id', 'establishment_id', 'level_id', 'program_id',
        'campaign_id', 'application_id', 'confirmation_id', 'correction_id', 'request_id', 'import_id', 'declared_by', 'ids',
        'status', 'from_status', 'is_active', 'is_current', 'field', 'check_key', 'check_status', 'changed_fields', 'filters', 'guard',
        'total_rows', 'valid_rows', 'warning_rows', 'rejected_rows', 'duplicate_rows', 'created_rows', 'updated_rows', 'declared_rows', 'skipped_rows',
        'type', 'sort_order', 'report', 'kind', 'role_name', 'previous_role', 'granted_permissions', 'revoked_permissions', 'denied_permissions', 'restored_permissions', 'trash_batch', 'disabled_types',
    ];

    public const MODULES = [
        'notifications' => 'Notifications',
        'authentication' => 'Connexions', 'identity' => 'Comptes internes', 'access' => 'Rôles et permissions', 'references' => 'Référentiels',
        'students' => 'Identités étudiantes', 'student_records' => 'Déclarations annuelles', 'student_imports' => 'Imports',
        'student_portal' => 'Portail étudiant', 'student_corrections' => 'Corrections', 'student_confirmations' => 'Confirmations étudiantes',
        'scholarship_campaigns' => 'Campagnes de bourse', 'scholarships' => 'Dossiers de bourse', 'audits' => 'Audit', 'reports' => 'Rapports', 'trash' => 'Corbeille', 'platform' => 'Plateforme',
    ];

    public const EVENTS = [
        'notification_preferences_updated' => 'Préférences de notification modifiées',
        'created' => 'Création', 'updated' => 'Modification', 'deleted' => 'Suppression', 'login' => 'Connexion', 'logout' => 'Déconnexion',
        'uploaded' => 'Fichier importé', 'analyzed' => 'Import analysé', 'committed' => 'Import confirmé', 'failed' => 'Échec d’import',
        'correction_requested' => 'Correction demandée', 'correction_cancelled' => 'Correction annulée', 'correction_approved' => 'Correction approuvée', 'correction_rejected' => 'Correction rejetée',
        'student_information_confirmed' => 'Informations confirmées par l’étudiant',
        'campaign_created' => 'Campagne créée', 'campaign_updated' => 'Campagne modifiée', 'campaign_status_changed' => 'Statut de campagne modifié',
        'application_received' => 'Dossier reçu', 'review_started' => 'Vérification commencée', 'check_updated' => 'Contrôle enregistré',
        'application_validated' => 'Dossier validé', 'application_needs_correction' => 'Correction de dossier demandée', 'application_rejected' => 'Dossier rejeté',
        'applications_exported' => 'Export des dossiers', 'audits_exported' => 'Export de l’audit', 'reports_exported' => 'Export de rapport',
        'role_permissions_updated' => 'Permissions du rôle modifiées', 'user_permissions_updated' => 'Droits du compte modifiés',
        'user_created' => 'Compte interne créé', 'user_updated' => 'Compte interne modifié', 'user_suspended' => 'Compte suspendu', 'user_reactivated' => 'Compte réactivé', 'user_invitation_sent' => 'Invitation envoyée', 'branding_updated' => 'Identité de la plateforme modifiée', 'trashed' => 'Déplacé dans la corbeille', 'restored' => 'Restauré', 'purged' => 'Purgé définitivement',
        'references_imported' => 'Import de référentiels', 'references_exported' => 'Export de référentiels',
        'link_requested' => 'Rattachement demandé', 'link_approved' => 'Rattachement approuvé', 'link_rejected' => 'Rattachement rejeté', 'automatic_link' => 'Rattachement automatique',
        'account_created' => 'Compte étudiant créé', 'google_associated' => 'Compte Google associé',
        'roles.attached' => 'Rôle attribué', 'roles.detached' => 'Rôle retiré', 'permissions.attached' => 'Permission attribuée', 'permissions.detached' => 'Permission retirée',
    ];

    /** @return array<string,mixed> */
    public static function summary(Activity $activity): array
    {
        return [
            'id' => $activity->id, 'uuid' => (string) $activity->getAttribute('uuid'),
            'at' => $activity->created_at?->toIso8601String(),
            'actor' => self::actor($activity->causer),
            'role' => self::role($activity->causer),
            'module' => (string) $activity->log_name, 'module_label' => self::MODULES[$activity->log_name] ?? (string) $activity->log_name,
            'event' => (string) $activity->event, 'event_label' => self::eventLabel($activity),
            'subject_type' => self::subjectType($activity->subject_type), 'subject_id' => $activity->subject_id,
        ];
    }

    /** @return array<string,mixed> */
    public static function detail(Activity $activity): array
    {
        $properties = $activity->properties?->all() ?? [];
        $attributes = is_array($properties['attributes'] ?? null) ? $properties['attributes'] : [];
        $old = is_array($properties['old'] ?? null) ? $properties['old'] : [];
        $changes = [];
        foreach (array_unique([...array_keys($attributes), ...array_keys($old)]) as $key) {
            $safe = in_array($key, self::SAFE_KEYS, true);
            $changes[] = ['field' => (string) $key, 'old' => $safe ? self::scalar($old[$key] ?? null) : null, 'new' => $safe ? self::scalar($attributes[$key] ?? null) : null, 'hidden' => ! $safe];
        }

        return [...self::summary($activity), 'metadata' => self::metadata($properties), 'changes' => $changes];
    }

    /**
     * Safe top-level properties only.
     *
     * @param  array<string,mixed>  $properties
     * @return list<array{key:string,value:string}>
     */
    public static function metadata(array $properties): array
    {
        $rows = [];
        foreach (self::SAFE_KEYS as $key) {
            if (! array_key_exists($key, $properties)) {
                continue;
            }
            $value = self::scalar($properties[$key]);
            if ($value !== null) {
                $rows[] = ['key' => $key, 'value' => $value];
            }
        }

        return $rows;
    }

    /** Scalars and flat lists of scalars only, bounded; nested structures are never rendered. */
    private static function scalar(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? 'oui' : 'non';
        }
        if (is_int($value) || is_float($value) || (is_string($value) && mb_strlen($value) <= 100)) {
            return (string) $value;
        }
        if ($value === []) {
            return 'aucun';
        }
        if (is_array($value) && array_is_list($value) && count($value) <= 50 && collect($value)->every(fn ($item) => is_int($item) || (is_string($item) && mb_strlen($item) <= 60))) {
            return implode(', ', $value);
        }

        return null;
    }

    /** Student accounts are shown by number only: their name or email is personal data. */
    public static function actor(?Model $causer): string
    {
        if (! $causer instanceof User) {
            return 'Système';
        }

        return $causer->hasRole('student') ? 'Compte étudiant n° '.$causer->id : (string) $causer->name;
    }

    public static function role(?Model $causer): ?string
    {
        if (! $causer instanceof User) {
            return null;
        }
        $labels = ['superadmin' => 'Superadmin', 'developer' => 'Développeur', 'central_scholarship_office' => 'Scolarité Centrale', 'establishment' => 'Établissement', 'student' => 'Étudiant'];

        return $causer->roles->pluck('name')->map(fn ($role) => $labels[$role] ?? $role)->implode(', ') ?: null;
    }

    public static function eventLabel(Activity $activity): string
    {
        return self::EVENTS[(string) $activity->event] ?? (string) ($activity->event ?: $activity->description);
    }

    public static function subjectType(?string $type): ?string
    {
        return $type === null ? null : class_basename($type);
    }
}
