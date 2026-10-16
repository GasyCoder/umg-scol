import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { isPageNavigation } from '@/lib/page-loading';

/** Foreground reads only. Mutations, polling, prefetching and partial reloads retain their own feedback. */
export function usePageLoading() {
    const [destination, setDestination] = useState<URL | null>(null);

    useEffect(() => {
        let activeId: string | null = null;
        let timer: ReturnType<typeof setTimeout> | undefined;
        const reset = () => {
            clearTimeout(timer);
            activeId = null;
            setDestination(null);
        };
        const stopStart = router.on('start', ({ detail: { visit } }) => {
            if (!isPageNavigation(visit)) return;
            clearTimeout(timer);
            activeId = visit.id;
            // Fast and prefetched navigations should not flash an artificial loading state.
            timer = setTimeout(() => {
                if (activeId === visit.id) setDestination(visit.url);
            }, 180);
        });
        const stopFinish = router.on('finish', ({ detail: { visit } }) => {
            if (activeId === visit.id) reset();
        });
        const stopHistory = router.on('navigate', ({ detail }) => {
            if (detail.type === 'history') reset();
        });
        return () => {
            clearTimeout(timer);
            stopStart();
            stopFinish();
            stopHistory();
        };
    }, []);

    return destination;
}
