import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';

// Test the shipped TypeScript without a second test runner or an added dependency.
const source = readFileSync(
    new URL('../../resources/js/lib/page-loading.ts', import.meta.url),
    'utf8',
);
const compiled = ts.transpileModule(source, {
    compilerOptions: {
        module: ts.ModuleKind.ESNext,
        target: ts.ScriptTarget.ES2022,
    },
}).outputText;
const { pageSkeletonFor, isPageNavigation } = await import(
    `data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`
);
const spec = (path, hasYear) =>
    pageSkeletonFor(new URL(path, 'http://scol.umg.test:8000'), hasYear);

const destinations = {
    '/dashboard': 'dashboard',
    '/reports': 'reports',
    '/students': 'student-cards',
    '/students?establishment_id=25&source=central': 'student-cards',
    '/students?view=list': 'student-list',
    '/students?academic_year_id=all': 'student-list',
    '/students?program_id=none': 'student-list',
    '/students?search=RABE': 'student-list',
    '/students?status=pending': 'student-list',
    '/students?holder=yes': 'student-list',
    '/students?verification=verified': 'student-list',
    '/students/create': 'student-form',
    '/students/student-uuid/edit': 'student-form',
    '/students/student-uuid': 'student-detail',
    '/students/student-uuid/records/create': 'record-form',
    '/student-records/record-uuid/edit': 'record-form',
    '/imports': 'list',
    '/imports/create': 'import-upload',
    '/imports/import-uuid': 'import-preview',
    '/references/programs': 'list',
    '/references/programs/create': 'reference-form',
    '/references/establishments/uuid/edit': 'reference-form',
    '/references/programs/import/token': 'import-preview',
    '/scholarships/reception': 'reception',
    '/scholarships/applications': 'list',
    '/scholarships/applications/uuid': 'scholarship-detail',
    '/users': 'list',
    '/users/create': 'user-form',
    '/users/uuid/edit': 'user-form',
    '/users/uuid': 'user-detail',
    '/corrections': 'list',
    '/corrections/uuid': 'detail',
    '/audits': 'list',
    '/audits/uuid': 'detail',
    '/notifications': 'list',
    '/trash': 'list',
    '/permissions': 'permissions',
    '/settings/profile': 'settings',
    '/settings/security': 'settings',
    '/settings/platform': 'settings',
    '/settings/appearance': 'settings',
    '/login': 'auth',
    '/forgot-password': 'auth',
    '/reset-password/token': 'auth',
    '/verify-email': 'auth',
    '/two-factor-challenge': 'auth',
    '/confirm-password': 'auth',
};
for (const [path, layout] of Object.entries(destinations)) {
    await test(`destination ${path} uses ${layout}`, () =>
        assert.equal(spec(path).layout, layout));
}
await test('no current year opens the student list; an explicitly chosen year opens cards', () => {
    assert.equal(spec('/students', false).layout, 'student-list');
    assert.equal(
        spec('/students?academic_year_id=15', false).layout,
        'student-cards',
    );
});
await test('unknown routes and file downloads retain the current page', () => {
    for (const path of [
        '/unknown',
        '/',
        '/students/export',
        '/students/template',
        '/imports/uuid/errors',
        '/references/levels/export',
        '/scholarships/applications/export',
        '/reports/students/export',
    ]) {
        assert.equal(spec(path), null, path);
    }
});
await test('table placeholders have the columns of their destination', () => {
    assert.equal(spec('/students?view=list').columns, 8);
    assert.equal(spec('/imports').columns, 6);
    assert.equal(spec('/notifications').columns, 3);
});
const visit = {
    method: 'get',
    async: false,
    prefetch: false,
    showProgress: true,
    only: [],
    except: [],
};
await test('foreground GET navigations show a skeleton', () =>
    assert.equal(isPageNavigation(visit), true));
for (const method of ['post', 'put', 'patch', 'delete']) {
    await test(`a ${method} action keeps its form and validation visible`, () =>
        assert.equal(isPageNavigation({ ...visit, method }), false));
}
for (const [name, changes] of Object.entries({
    polling: { async: true },
    prefetch: { prefetch: true },
    notifications: { only: ['notifications', 'tasks'] },
    partial: { except: ['records'] },
    silent: { showProgress: false },
})) {
    await test(`${name} does not hide current content`, () =>
        assert.equal(isPageNavigation({ ...visit, ...changes }), false));
}

await test('removed campaigns have no navigation skeleton', () =>
    assert.equal(spec('/scholarships/campaigns'), null));
