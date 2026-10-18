<?php

namespace App\Support\Permissions;

use App\Models\User;

class PermissionCatalogue
{
    public const STUDENT = ['students.view', 'student_records.view', 'corrections.create', 'corrections.view'];

    public const PROTECTED = ['permissions.manage', 'roles.manage', 'users.manage'];

    public const ROLES = ['superadmin' => 'Superadministrateur', 'developer' => 'Développeur', 'central_scholarship_office' => 'Scolarité Centrale', 'establishment' => 'Établissement', 'student' => 'Étudiant'];

    /** @return list<array{name:string,label:string,description:string,group:string}> */
    public static function rows(): array
    {
        $groups = ['students' => 'Étudiants', 'student_records' => 'Situations annuelles', 'imports' => 'Imports', 'corrections' => 'Corrections', 'scholarships' => 'Bourses', 'establishments' => 'Référentiels', 'academic_years' => 'Référentiels', 'levels' => 'Référentiels', 'programs' => 'Référentiels', 'users' => 'Administration', 'roles' => 'Administration', 'permissions' => 'Administration', 'branding' => 'Administration', 'audits' => 'Audit', 'dashboard' => 'Tableau de bord et rapports', 'reports' => 'Tableau de bord et rapports', 'notifications' => 'Notifications', 'trash' => 'Corbeille', 'student_links' => 'Portail étudiant'];
        $objects = ['students' => 'les étudiants', 'student_records' => 'les situations annuelles', 'imports' => 'les imports', 'corrections' => 'les corrections', 'scholarships' => 'les dossiers de bourse', 'establishments' => 'les établissements', 'academic_years' => 'les années universitaires', 'levels' => 'les niveaux', 'programs' => 'les mentions et parcours', 'users' => 'les comptes', 'roles' => 'les rôles', 'permissions' => 'les permissions', 'branding' => 'l’identité de la plateforme', 'audits' => 'les audits', 'trash' => 'la corbeille', 'student_links' => 'les rattachements étudiants'];
        $verbs = ['view' => 'Consulter', 'viewAny' => 'Lister', 'create' => 'Créer', 'update' => 'Modifier', 'deactivate' => 'Désactiver', 'export' => 'Exporter', 'manage' => 'Administrer', 'commit' => 'Confirmer', 'export_errors' => 'Exporter les erreurs de', 'review' => 'Examiner', 'receive' => 'Recevoir', 'validate' => 'Valider', 'request_correction' => 'Demander une correction pour', 'reject' => 'Rejeter', 'purge' => 'Purger définitivement'];
        // Full labels of the central office permissions (verb + object would read badly).
        $labels = ['students.register_pending' => 'Saisir un étudiant en attente de la liste de l’établissement', 'student_records.verify' => 'Valider les situations trouvées dans la liste de l’établissement', 'imports.pnes' => 'Importer la base PNES des boursiers', 'imports.preinscription' => 'Importer les résultats de préinscription',
            'scholarships.labels' => 'Imprimer la fiche d’étiquettes DDB vierges',
            'students.notify' => 'Prévenir un étudiant par email (informations manquantes ou à vérifier)',
            'dashboard.view' => 'Consulter le tableau de bord', 'reports.view' => 'Consulter les rapports de son périmètre (plateforme ou établissement)',
            'reports.view_own' => 'Consulter le rapport de ses propres activités', 'notifications.view' => 'Recevoir et consulter les notifications'];
        $rows = [];
        foreach (config('access.permissions') as $name) {
            [$prefix,$verb] = explode('.', $name, 2);
            $label = $labels[$name] ?? ($verbs[$verb] ?? $verb).' '.($objects[$prefix] ?? $prefix);
            $rows[] = ['name' => $name, 'label' => $label, 'description' => $prefix === 'trash' ? 'Opération globale réservée aux comptes superadmin/developer sans rôle établissement ; dépendances protégées.' : 'Autorise cette action dans le périmètre serveur prévu pour le rôle.', 'group' => $groups[$prefix] ?? 'Autres'];
        }

        return $rows;
    }

    /**
     * Roles shown in the permission screens: the developer role is visible to developers only
     * (it stays in the audit trail).
     *
     * @return list<string>
     */
    public static function visibleRoles(User $actor): array
    {
        return array_values(array_filter(array_map('strval', array_keys(config('access.roles'))), fn (string $role) => $role !== 'developer' || $actor->hasRole('developer')));
    }

    /** @param array<int,string> $permissions */
    public static function version(array $permissions): string
    {
        sort($permissions);

        return hash('sha256', implode('|', $permissions));
    }
}
