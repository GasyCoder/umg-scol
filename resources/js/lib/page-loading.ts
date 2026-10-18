export type SkeletonLayout =
    | 'dashboard'
    | 'reports'
    | 'student-cards'
    | 'student-list'
    | 'student-detail'
    | 'student-form'
    | 'record-form'
    | 'reference-form'
    | 'list'
    | 'detail'
    | 'user-form'
    | 'user-detail'
    | 'import-upload'
    | 'import-preview'
    | 'scholarship-detail'
    | 'reception'
    | 'permissions'
    | 'settings'
    | 'auth';

export type PageSkeletonSpec = {
    layout: SkeletonLayout;
    columns?: number;
    fields?: number;
};

/** Destination URL, never the previous page, determines the loading structure. */
export function pageSkeletonFor(
    url: URL,
    hasCurrentYear?: boolean,
): PageSkeletonSpec | null {
    const path = url.pathname.replace(/\/$/, '') || '/';
    const query = url.searchParams;
    // File downloads are not pages and must never hide the current content.
    if (/\/(export|template|errors)$/.test(path)) return null;
    if (path === '/dashboard') return { layout: 'dashboard' };
    if (path === '/reports') return { layout: 'reports' };
    if (path === '/students') {
        const list =
            (hasCurrentYear === false && !query.has('academic_year_id')) ||
            query.get('view') === 'list' ||
            query.get('academic_year_id') === 'all' ||
            ['search', 'status', 'holder', 'verification', 'program_id'].some(
                (key) => !!query.get(key),
            );
        return { layout: list ? 'student-list' : 'student-cards', columns: 8 };
    }
    if (path === '/students/create' || /^\/students\/[^/]+\/edit$/.test(path))
        return { layout: 'student-form', fields: 6 };
    if (
        /^\/students\/[^/]+\/records\/create$/.test(path) ||
        /^\/student-records\/[^/]+\/edit$/.test(path)
    )
        return { layout: 'record-form', fields: 8 };
    if (/^\/students\/[^/]+$/.test(path)) return { layout: 'student-detail' };
    if (/^\/references\/[^/]+\/import\//.test(path))
        return { layout: 'import-preview', columns: 5 };
    if (/^\/references\/[^/]+\/(create|[^/]+\/edit)$/.test(path))
        return {
            layout: 'reference-form',
            fields: path.includes('/establishments/') ? 8 : 5,
        };
    if (/^\/references\/[^/]+$/.test(path))
        return { layout: 'list', columns: 5 };
    if (path === '/imports/create') return { layout: 'import-upload' };
    if (path === '/imports') return { layout: 'list', columns: 6 };
    if (/^\/imports\/[^/]+$/.test(path))
        return { layout: 'import-preview', columns: 6 };
    if (path === '/scholarships/reception') return { layout: 'reception' };
    if (path === '/scholarships/applications')
        return { layout: 'list', columns: 7 };
    if (/^\/scholarships\/applications\/[^/]+$/.test(path))
        return { layout: 'scholarship-detail' };
    if (path === '/users/create' || /^\/users\/[^/]+\/edit$/.test(path))
        return { layout: 'user-form', fields: 6 };
    if (path === '/users') return { layout: 'list', columns: 6 };
    if (/^\/users\/[^/]+$/.test(path)) return { layout: 'user-detail' };
    if (path === '/permissions') return { layout: 'permissions' };
    if (
        path === '/corrections' ||
        path === '/audits' ||
        path === '/trash' ||
        path === '/notifications'
    )
        return { layout: 'list', columns: path === '/notifications' ? 3 : 5 };
    if (/^\/(corrections|audits)\/[^/]+$/.test(path))
        return { layout: 'detail' };
    if (path.startsWith('/settings/'))
        return {
            layout: 'settings',
            fields: path.endsWith('/appearance') ? 3 : 6,
        };
    if (
        /^\/(login|register|forgot-password|reset-password(?:\/[^/]+)?|verify-email|two-factor-challenge|confirm-password)$/.test(
            path,
        )
    )
        return {
            layout: 'auth',
            fields:
                path === '/login'
                    ? 2
                    : path.startsWith('/reset-password') || path === '/register'
                      ? 4
                      : 1,
        };
    // No invented generic skeleton for an unknown page.
    return null;
}

export function isPageNavigation(visit: {
    method: string;
    async: boolean;
    prefetch: boolean;
    showProgress: boolean;
    only: string[];
    except: string[];
}): boolean {
    return (
        visit.method.toLowerCase() === 'get' &&
        !visit.async &&
        !visit.prefetch &&
        visit.showProgress &&
        visit.only.length === 0 &&
        visit.except.length === 0
    );
}
