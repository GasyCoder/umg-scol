import { Skeleton } from '@/components/ui/skeleton';
import type { PageSkeletonSpec } from '@/lib/page-loading';
import { cn } from '@/lib/utils';

function Line({ className }: { className?: string }) {
    return (
        <Skeleton
            className={cn(
                'h-4 bg-muted-foreground/15 motion-reduce:animate-none',
                className,
            )}
        />
    );
}

function Header({ compact = false }: { compact?: boolean }) {
    return (
        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div className="flex min-w-0 gap-4">
                {!compact && (
                    <Line className="hidden size-12 shrink-0 rounded-xl sm:block" />
                )}
                <div className="min-w-0 space-y-3">
                    <Line className="h-7 w-52 max-w-full" />
                    <Line className="w-72 max-w-full" />
                </div>
            </div>
            <div className="flex shrink-0 gap-2">
                <Line className="h-9 w-28" />
                <Line className="h-9 w-32" />
            </div>
        </div>
    );
}

function Fields({
    count = 6,
    single = false,
    display = false,
}: {
    count?: number;
    single?: boolean;
    display?: boolean;
}) {
    return (
        <div
            className={cn('grid gap-x-6 gap-y-5', !single && 'sm:grid-cols-2')}
        >
            {Array.from({ length: count }, (_, index) => (
                <div key={index} className="min-w-0 space-y-2">
                    <Line className="h-3 w-24" />
                    <Line className={display ? 'h-4 w-3/4' : 'h-10 w-full'} />
                </div>
            ))}
        </div>
    );
}

function Panel({
    fields = 6,
    form = false,
}: {
    fields?: number;
    form?: boolean;
}) {
    return (
        <div className="surface space-y-5 p-4 sm:p-6">
            <Line className="h-5 w-40" />
            <Fields count={fields} display={!form} />
        </div>
    );
}

function Filters() {
    return (
        <div className="surface flex flex-col gap-3 p-4 lg:flex-row">
            <Line className="h-9 w-full lg:flex-1" />
            <div className="flex gap-3">
                <Line className="h-9 w-32 sm:w-44" />
                <Line className="h-9 w-24 sm:w-36" />
            </div>
        </div>
    );
}

function Table({ columns = 5, rows = 5 }: { columns?: number; rows?: number }) {
    return (
        <div className="surface overflow-hidden" data-skeleton-section="table">
            <div className="overflow-x-auto">
                <div className="min-w-[40rem]">
                    <div
                        className="grid gap-4 border-b bg-muted/30 px-4 py-4"
                        style={{
                            gridTemplateColumns: `2fr repeat(${columns - 1}, minmax(0, 1fr))`,
                        }}
                    >
                        {Array.from({ length: columns }, (_, index) => (
                            <Line key={index} className="h-3 w-3/4" />
                        ))}
                    </div>
                    {Array.from({ length: rows }, (_, row) => (
                        <div
                            key={row}
                            className="grid items-center gap-4 border-b px-4 py-4 last:border-0"
                            style={{
                                gridTemplateColumns: `2fr repeat(${columns - 1}, minmax(0, 1fr))`,
                            }}
                        >
                            {Array.from({ length: columns }, (_, column) => (
                                <div key={column} className="space-y-2">
                                    <Line
                                        className={
                                            column === columns - 1
                                                ? 'h-6 w-16 rounded-full'
                                                : 'w-4/5'
                                        }
                                    />
                                    {column === 0 && (
                                        <Line className="h-3 w-1/2" />
                                    )}
                                </div>
                            ))}
                        </div>
                    ))}
                </div>
            </div>
            <div className="flex justify-end gap-2 border-t p-3">
                <Line className="h-8 w-8" />
                <Line className="h-8 w-8" />
                <Line className="h-8 w-8" />
            </div>
        </div>
    );
}

function Tabs() {
    return (
        <div className="flex gap-2 overflow-hidden">
            <Line className="h-9 w-32 shrink-0" />
            <Line className="h-9 w-32 shrink-0" />
            <Line className="h-9 w-24 shrink-0" />
        </div>
    );
}

function Steps() {
    return (
        <div
            className="surface grid grid-cols-3 gap-4 p-4 sm:grid-cols-5"
            data-skeleton-section="steps"
        >
            {Array.from({ length: 5 }, (_, index) => (
                <div key={index} className="flex min-w-0 items-center gap-2">
                    <Line className="size-8 shrink-0 rounded-full" />
                    <Line className="h-3 w-16 max-w-full" />
                </div>
            ))}
        </div>
    );
}

function Counters({ count = 4 }: { count?: number }) {
    return (
        <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
            {Array.from({ length: count }, (_, index) => (
                <div key={index} className="surface space-y-3 p-4">
                    <Line className="h-3 w-24" />
                    <Line className="h-8 w-16" />
                </div>
            ))}
        </div>
    );
}

function Identity() {
    return (
        <div className="surface space-y-5 overflow-hidden">
            <div className="h-20 bg-muted/40" />
            <div className="space-y-4 px-5 pb-5">
                <Line className="size-16 rounded-full" />
                <Line className="h-6 w-40" />
                <Line className="w-32" />
                <Fields count={4} single />
            </div>
        </div>
    );
}

function Form({
    fields = 6,
    steps = false,
}: {
    fields?: number;
    steps?: boolean;
}) {
    return (
        <>
            {steps && <Steps />}
            <div className="surface space-y-6 p-4 sm:p-6">
                <Line className="h-5 w-40" />
                <Fields count={fields} />
                <div className="flex justify-end gap-2 border-t pt-4">
                    <Line className="h-9 w-24" />
                    <Line className="h-9 w-32" />
                </div>
            </div>
        </>
    );
}

function Content({ spec }: { spec: PageSkeletonSpec }) {
    switch (spec.layout) {
        case 'dashboard':
            return (
                <>
                    <div className="surface flex items-center justify-between gap-8 p-6 sm:p-8">
                        <div className="w-full max-w-lg space-y-4">
                            <Line className="h-8 w-3/4" />
                            <Line className="w-full" />
                            <Line className="h-8 w-40" />
                        </div>
                        <Line className="hidden h-36 w-52 md:block" />
                    </div>
                    <Counters />
                    <div className="grid gap-4 lg:grid-cols-2">
                        {[0, 1, 2, 3].map((index) => (
                            <div key={index} className="surface space-y-5 p-5">
                                <Line className="h-5 w-40" />
                                {[0, 1, 2, 3].map((_, row) => (
                                    <div key={row} className="space-y-2">
                                        <Line className="h-3 w-24" />
                                        <Line
                                            className={
                                                row % 2 === 0
                                                    ? 'h-5 w-3/5'
                                                    : 'h-5 w-4/5'
                                            }
                                        />
                                    </div>
                                ))}
                            </div>
                        ))}
                    </div>
                </>
            );
        case 'reports':
            return (
                <>
                    <Filters />
                    <Counters />
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Table columns={3} />
                        <Table columns={3} />
                    </div>
                </>
            );
        case 'student-cards':
        case 'student-list':
            return (
                <>
                    <div className="flex flex-wrap justify-between gap-3">
                        <Line className="h-10 w-72 max-w-full" />
                        <Line className="h-10 w-52" />
                    </div>
                    <Line className="h-11 w-full" />
                    <Filters />
                    {spec.layout === 'student-list' ? (
                        <Table columns={8} />
                    ) : (
                        <>
                            <div className="flex justify-between">
                                <Line className="h-5 w-36" />
                                <Line className="h-8 w-28" />
                            </div>
                            <div
                                className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
                                data-skeleton-section="cards"
                            >
                                {[0, 1, 2, 3, 4, 5].map((index) => (
                                    <div
                                        key={index}
                                        className="surface flex items-center gap-3 p-4"
                                    >
                                        <Line className="size-10 shrink-0" />
                                        <div className="flex-1 space-y-2">
                                            <Line className="w-full" />
                                            <Line className="h-3 w-14" />
                                        </div>
                                        <Line className="h-6 w-8" />
                                    </div>
                                ))}
                            </div>
                        </>
                    )}
                </>
            );
        case 'student-detail':
            return (
                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="space-y-5">
                        <div className="surface flex items-center gap-4 p-6">
                            <Line className="size-20 shrink-0 rounded-full" />
                            <div className="w-full space-y-3">
                                <Line className="h-6 w-52 max-w-full" />
                                <Line className="w-40" />
                            </div>
                        </div>
                        <Tabs />
                        <Panel fields={8} />
                    </div>
                    <div className="space-y-4">
                        <Panel fields={4} />
                        <Panel fields={2} />
                    </div>
                </div>
            );
        case 'user-detail':
            return (
                <>
                    <div className="surface flex items-center gap-4 p-6">
                        <Line className="size-20 shrink-0 rounded-full" />
                        <Line className="h-6 w-52" />
                    </div>
                    <div className="grid gap-6 lg:grid-cols-3">
                        <div className="lg:col-span-2">
                            <Panel fields={6} />
                        </div>
                        <Panel fields={4} />
                    </div>
                </>
            );
        case 'detail':
            return (
                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-5 lg:col-span-2">
                        <Panel />
                        <Panel fields={4} />
                    </div>
                    <Panel fields={4} />
                </div>
            );
        case 'scholarship-detail':
            return (
                <div className="grid items-start gap-6 lg:grid-cols-3">
                    <div className="space-y-5">
                        <Panel fields={4} />
                        <Panel fields={2} />
                    </div>
                    <div className="space-y-5 lg:col-span-2">
                        <Panel fields={8} />
                        <Table columns={4} />
                    </div>
                </div>
            );
        case 'student-form':
            return <Form fields={spec.fields} steps />;
        case 'record-form':
        case 'reference-form':
            return (
                <div className="max-w-4xl">
                    <Form fields={spec.fields} />
                </div>
            );
        case 'user-form':
            return (
                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <Form fields={spec.fields} />
                    </div>
                    <Panel fields={3} />
                </div>
            );
        case 'import-upload':
            return (
                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="surface space-y-6 p-6 lg:col-span-2">
                        <Tabs />
                        <Fields count={2} />
                        <Line className="h-48 w-full rounded-xl" />
                        <Line className="ml-auto h-10 w-40" />
                    </div>
                    <Panel fields={4} />
                </div>
            );
        case 'import-preview':
            return (
                <>
                    <Steps />
                    <div className="surface flex flex-wrap justify-between gap-4 p-5">
                        <div className="space-y-3">
                            <Line className="h-5 w-44" />
                            <Line className="w-60" />
                        </div>
                        <Line className="h-10 w-40" />
                    </div>
                    <Counters />
                    <Table columns={spec.columns ?? 6} />
                </>
            );
        case 'reception':
            return (
                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="surface space-y-6 p-6 lg:col-span-2">
                        <Fields count={1} single />
                        <Line className="h-10 w-full" />
                        <div className="space-y-3">
                            {[0, 1, 2].map((index) => (
                                <Line key={index} className="h-20 w-full" />
                            ))}
                        </div>
                    </div>
                    <Panel fields={4} />
                </div>
            );
        case 'permissions':
            return (
                <>
                    <Filters />
                    <Table columns={6} rows={8} />
                </>
            );
        case 'settings':
            return (
                <div className="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)] lg:gap-8">
                    <div className="flex gap-2 overflow-hidden lg:flex-col">
                        {[0, 1, 2, 3].map((index) => (
                            <Line
                                key={index}
                                className="h-14 w-44 shrink-0 lg:w-full"
                            />
                        ))}
                    </div>
                    <div className="space-y-6">
                        <Panel fields={spec.fields} form />
                        <Panel fields={2} form />
                    </div>
                </div>
            );
        case 'auth':
            return (
                <div className="space-y-6">
                    <Fields count={spec.fields} single />
                    <Line className="h-10 w-full" />
                    <Line className="mx-auto h-3 w-40" />
                </div>
            );
        case 'list':
            return (
                <>
                    <Filters />
                    <Table columns={spec.columns} />
                </>
            );
    }
}

/** No interactive fake controls or invented text; layout follows the destination page. */
export function PageSkeleton({ spec }: { spec: PageSkeletonSpec }) {
    return (
        <div
            aria-hidden="true"
            data-skeleton-layout={spec.layout}
            className={cn(
                'min-w-0 space-y-6',
                spec.layout !== 'auth' && 'p-4 md:p-6 lg:p-8',
                spec.layout.startsWith('student-') && 'space-y-4',
            )}
        >
            {spec.layout !== 'auth' && spec.layout !== 'dashboard' && (
                <Header compact={spec.layout.startsWith('student-')} />
            )}
            <Content spec={spec} />
        </div>
    );
}

/** Portal boot: the login card until the session is known; identity cards once it is linked. */
export function StudentPortalSkeleton({
    profile = false,
}: {
    profile?: boolean;
}) {
    return (
        <div
            role="status"
            aria-label="Chargement du portail étudiant"
            aria-busy="true"
            data-portal-loading={profile ? 'profile' : 'session'}
        >
            <span className="sr-only">Chargement des informations…</span>
            <div
                aria-hidden="true"
                className={profile ? 'space-y-6' : 'mx-auto max-w-lg space-y-4'}
            >
                {profile ? (
                    <>
                        <div className="surface space-y-4 p-6 sm:p-8">
                            <Line className="h-8 w-64 max-w-full" />
                            <Line className="w-44" />
                        </div>
                        <div className="grid items-start gap-6 xl:grid-cols-[17rem_minmax(0,1fr)_18rem]">
                            <Identity />
                            <div className="space-y-5">
                                <Panel fields={6} />
                                <Panel fields={4} />
                            </div>
                            <Panel fields={4} />
                        </div>
                    </>
                ) : (
                    <div className="surface overflow-hidden rounded-2xl">
                        <div className="space-y-4 border-b bg-muted/30 p-6">
                            <Line className="mx-auto h-28 w-40" />
                            <Line className="mx-auto h-7 w-64 max-w-full" />
                            <Line className="mx-auto w-56 max-w-full" />
                        </div>
                        <div className="space-y-6 p-6">
                            <Tabs />
                            <Fields count={1} single />
                            <Line className="h-11 w-full" />
                            <Line className="h-11 w-full" />
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
