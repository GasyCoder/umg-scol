<?php

$students = ['students.view', 'students.viewAny', 'students.create', 'students.update', 'students.deactivate', 'students.export'];
// Entry by the central office, pending the establishment's list.
$centralEntry = ['students.register_pending'];
$records = ['student_records.view', 'student_records.create', 'student_records.update', 'student_records.verify'];
$imports = ['imports.view', 'imports.create', 'imports.commit', 'imports.export_errors'];
// Files of the central office: national scholarship base and pre-registration results.
$centralImports = ['imports.pnes', 'imports.preinscription'];
$corrections = ['corrections.create', 'corrections.view', 'corrections.review'];
$scholarships = ['scholarships.view', 'scholarships.receive', 'scholarships.review', 'scholarships.validate', 'scholarships.request_correction', 'scholarships.reject', 'scholarships.export'];
// Sheet of blank DDB labels: not part of the central office set, granted through the permission matrix.
$labels = ['scholarships.labels'];
$administration = ['establishments.manage', 'academic_years.manage', 'levels.manage', 'programs.manage', 'users.manage', 'roles.manage', 'permissions.manage', 'branding.manage'];
$audits = ['audits.view', 'audits.export'];
// Dashboard, reports (perimeter, or the account's own activity only) and notifications.
$steering = ['dashboard.view', 'reports.view', 'reports.view_own', 'notifications.view'];
// Trash: move to the trash and restore (manage), permanent deletion (purge).
$trash = ['trash.manage', 'trash.purge'];
// Emails asking a student to complete or check their record: superadmin by default, others through the matrix.
$notify = ['students.notify'];
$permissions = [...$students, ...$notify, ...$centralEntry, ...$records, ...$imports, ...$centralImports, ...$corrections, ...$scholarships, ...$labels, ...$administration, ...$audits, ...$steering, ...$trash, 'student_links.review'];

return [
    'guard' => 'web',
    'permissions' => $permissions,
    'roles' => [
        'superadmin' => $permissions,
        'developer' => $permissions,
        'central_scholarship_office' => [
            // students.update: completes a missing identity field at the reception counter (global, audited).
            'students.view', 'students.viewAny', 'students.update', 'students.export',
            'student_records.view', 'corrections.view', 'student_links.review', ...$scholarships,
            ...$centralEntry, 'student_records.verify', ...$centralImports,
            'dashboard.view', 'reports.view_own', 'notifications.view',
        ],
        'establishment' => [...$students, ...array_values(array_diff($records, ['student_records.verify'])), ...$imports, 'student_links.review', 'corrections.view', 'corrections.review', 'dashboard.view', 'reports.view', 'notifications.view'],
        'student' => ['students.view', 'student_records.view', 'corrections.create', 'corrections.view'],
    ],
];
