<?php

$students = ['students.view', 'students.viewAny', 'students.create', 'students.update', 'students.deactivate', 'students.export'];
$records = ['student_records.view', 'student_records.create', 'student_records.update'];
$imports = ['imports.view', 'imports.create', 'imports.commit', 'imports.export_errors'];
$corrections = ['corrections.create', 'corrections.view', 'corrections.review'];
$scholarships = ['scholarships.view', 'scholarships.receive', 'scholarships.review', 'scholarships.validate', 'scholarships.request_correction', 'scholarships.reject', 'scholarships.export'];
$administration = ['establishments.manage', 'academic_years.manage', 'levels.manage', 'programs.manage', 'campaigns.manage', 'users.manage', 'roles.manage', 'permissions.manage'];
$audits = ['audits.view', 'audits.export'];
$permissions = [...$students, ...$records, ...$imports, ...$corrections, ...$scholarships, ...$administration, ...$audits, 'student_links.review'];

return [
    'guard' => 'web',
    'permissions' => $permissions,
    'roles' => [
        'superadmin' => $permissions,
        'developer' => $permissions,
        'central_scholarship_office' => [
            'students.view', 'students.viewAny', 'students.export',
            'student_records.view', 'corrections.view', 'student_links.review', ...$scholarships,
        ],
        'establishment' => [...$students, ...$records, ...$imports, 'student_links.review', 'corrections.view', 'corrections.review'],
        'student' => ['students.view', 'student_records.view', 'corrections.create', 'corrections.view'],
    ],
];
