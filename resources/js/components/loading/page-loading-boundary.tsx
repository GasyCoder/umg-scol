import { usePage } from '@inertiajs/react';
import { createContext, useContext } from 'react';
import type { ReactNode } from 'react';
import { usePageLoading } from '@/hooks/use-page-loading';
import { pageSkeletonFor } from '@/lib/page-loading';
import { PageSkeleton } from './page-skeleton';

const LoadingContext = createContext<URL | null>(null);

/** One subscription shared by all persistent layouts. */
export function PageLoadingProvider({ children }: { children: ReactNode }) {
    const destination = usePageLoading();
    return (
        <LoadingContext.Provider value={destination}>
            {children}
        </LoadingContext.Provider>
    );
}

export function PageLoadingBoundary({
    children,
    shell = 'internal',
}: {
    children: ReactNode;
    shell?: 'internal' | 'auth';
}) {
    const destination = useContext(LoadingContext);
    const page = usePage();
    const options = page.props.options as
        | { academicYears?: { is_current?: boolean; is_active?: boolean }[] }
        | undefined;
    const hasCurrentYear = options?.academicYears?.some(
        (year) => year.is_current && year.is_active,
    );
    const target = destination
        ? pageSkeletonFor(destination, hasCurrentYear)
        : null;
    const current = pageSkeletonFor(new URL(page.url, 'http://inertia.local'));
    const changesShell =
        target && (shell === 'auth') !== (target.layout === 'auth');
    const spec = changesShell ? current : target;
    const loading = spec !== null;
    return (
        <div
            aria-busy={loading}
            data-page-loading={loading ? spec.layout : undefined}
        >
            <span role="status" aria-live="polite" className="sr-only">
                {loading ? 'Chargement de la page…' : ''}
            </span>
            {loading && <PageSkeleton spec={spec} />}
            {/* Keep the page mounted: unsaved values and validation errors survive failed/cancelled reads. */}
            <div hidden={loading} inert={loading || undefined}>
                {children}
            </div>
        </div>
    );
}
