<?php

namespace App\Support\Permissions;

class PermissionCatalogue
{
    public const STUDENT = ['students.view', 'student_records.view', 'corrections.create', 'corrections.view'];

    public const PROTECTED = ['permissions.manage', 'roles.manage', 'users.manage'];

    public const ROLES = ['superadmin' => 'Superadministrateur', 'developer' => 'Développeur', 'central_scholarship_office' => 'Scolarité Centrale', 'establishment' => 'Établissement', 'student' => 'Étudiant'];

    /** @return list<array{name:string,label:string,description:string,group:string}> */
    public static function rows(): array
    {
        $groups = ['students' => 'Étudiants', 'student_records' => 'Déclarations annuelles', 'imports' => 'Imports', 'corrections' => 'Corrections', 'scholarships' => 'Bourses', 'establishments' => 'Référentiels', 'academic_years' => 'Référentiels', 'levels' => 'Référentiels', 'programs' => 'Référentiels', 'campaigns' => 'Bourses', 'users' => 'Administration', 'roles' => 'Administration', 'permissions' => 'Administration', 'audits' => 'Audit', 'trash' => 'Corbeille', 'student_links' => 'Portail étudiant'];
        $objects = ['students' => 'les étudiants', 'student_records' => 'les déclarations annuelles', 'imports' => 'les imports', 'corrections' => 'les corrections', 'scholarships' => 'les dossiers de bourse', 'establishments' => 'les établissements', 'academic_years' => 'les années universitaires', 'levels' => 'les niveaux', 'programs' => 'les mentions et parcours', 'campaigns' => 'les campagnes', 'users' => 'les comptes', 'roles' => 'les rôles', 'permissions' => 'les permissions', 'audits' => 'les audits', 'trash' => 'la corbeille', 'student_links' => 'les rattachements étudiants'];
        $verbs = ['view' => 'Consulter', 'viewAny' => 'Lister', 'create' => 'Créer', 'update' => 'Modifier', 'deactivate' => 'Désactiver', 'export' => 'Exporter', 'manage' => 'Administrer', 'commit' => 'Confirmer', 'export_errors' => 'Exporter les erreurs de', 'review' => 'Examiner', 'receive' => 'Recevoir', 'validate' => 'Valider', 'request_correction' => 'Demander une correction pour', 'reject' => 'Rejeter', 'purge' => 'Purger définitivement'];
        $rows = [];
        foreach (config('access.permissions') as $name) {
            [$prefix,$verb] = explode('.', $name, 2);
            $label = ($verbs[$verb] ?? $verb).' '.($objects[$prefix] ?? $prefix);
            $rows[] = ['name' => $name, 'label' => $label, 'description' => $prefix === 'trash' ? 'Opération globale réservée aux comptes superadmin/developer sans rôle établissement ; dépendances protégées.' : 'Autorise cette action dans le périmètre serveur prévu pour le rôle.', 'group' => $groups[$prefix] ?? 'Autres'];
        }

        return $rows;
    }

    /** @param array<int,string> $permissions */
    public static function version(array $permissions): string
    {
        sort($permissions);

        return hash('sha256', implode('|', $permissions));
    }
}
