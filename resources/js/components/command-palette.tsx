import { router } from '@inertiajs/react';
import { CornerDownLeft, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { useNavigation } from '@/hooks/use-navigation';
import { cn, toUrl } from '@/lib/utils';

const normalize = (value: string) =>
    value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();

/**
 * Page search (Ctrl/⌘ K): jumps to any menu entry the account can see.
 * It only navigates; it never searches records, so no data leaves the server's pages.
 */
export function CommandPalette() {
    const groups = useNavigation();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const listRef = useRef<HTMLUListElement>(null);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                event.key.toLowerCase() === 'k' &&
                (event.metaKey || event.ctrlKey)
            ) {
                event.preventDefault();
                setOpen((value) => !value);
            }
        };
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    const results = useMemo(() => {
        const term = normalize(query.trim());
        return groups.flatMap((group) =>
            group.items
                .filter(
                    (item) =>
                        term === '' ||
                        normalize(`${item.title} ${group.label}`).includes(
                            term,
                        ),
                )
                .map((item) => ({ ...item, group: group.label })),
        );
    }, [groups, query]);

    const go = (index: number) => {
        const item = results[index];
        if (!item) return;
        setOpen(false);
        router.visit(toUrl(item.href));
    };

    const onOpenChange = (value: boolean) => {
        setOpen(value);
        setQuery('');
        setActive(0);
    };

    return (
        <>
            <button
                type="button"
                onClick={() => onOpenChange(true)}
                className="hidden h-10 w-64 shrink-0 items-center gap-2 rounded-full border bg-muted/60 px-4 text-sm text-muted-foreground transition-colors hover:border-primary/40 hover:bg-card focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none md:flex lg:w-80"
            >
                <Search className="size-4" aria-hidden="true" />
                <span className="flex-1 text-left">Aller à une page…</span>
                <kbd className="rounded-md border bg-card px-1.5 py-0.5 font-sans text-[10px] font-semibold">
                    Ctrl K
                </kbd>
            </button>
            <button
                type="button"
                onClick={() => onOpenChange(true)}
                className="flex size-10 items-center justify-center rounded-full border bg-card text-muted-foreground shadow-xs hover:bg-accent hover:text-primary md:hidden"
                aria-label="Aller à une page"
            >
                <Search className="size-5" />
            </button>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="top-[15%] translate-y-0 gap-0 overflow-hidden rounded-2xl p-0 sm:max-w-xl [&>button:last-child]:hidden">
                    <DialogTitle className="sr-only">
                        Aller à une page
                    </DialogTitle>
                    <DialogDescription className="sr-only">
                        Tapez le nom d’une page puis Entrée.
                    </DialogDescription>
                    <div className="flex items-center gap-3 border-b px-4">
                        <Search
                            className="size-5 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <input
                            autoFocus
                            value={query}
                            onChange={(event) => {
                                setQuery(event.target.value);
                                setActive(0);
                            }}
                            onKeyDown={(event) => {
                                if (event.key === 'ArrowDown') {
                                    event.preventDefault();
                                    setActive((i) =>
                                        Math.min(i + 1, results.length - 1),
                                    );
                                } else if (event.key === 'ArrowUp') {
                                    event.preventDefault();
                                    setActive((i) => Math.max(i - 1, 0));
                                } else if (event.key === 'Enter') {
                                    event.preventDefault();
                                    go(active);
                                }
                            }}
                            placeholder="Rechercher une page : dossiers, imports, niveaux…"
                            className="h-14 flex-1 bg-transparent text-base outline-none placeholder:text-muted-foreground"
                            role="combobox"
                            aria-expanded="true"
                            aria-controls="command-results"
                            aria-activedescendant={
                                results[active]
                                    ? `command-${active}`
                                    : undefined
                            }
                        />
                        <kbd className="rounded-md border px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground">
                            Échap
                        </kbd>
                    </div>
                    <ul
                        id="command-results"
                        ref={listRef}
                        role="listbox"
                        className="max-h-[min(24rem,60vh)] overflow-y-auto p-2"
                    >
                        {results.length === 0 && (
                            <li className="px-3 py-10 text-center text-sm text-muted-foreground">
                                Aucune page ne correspond à « {query} ».
                            </li>
                        )}
                        {results.map((item, index) => (
                            <li
                                key={`${item.group}-${item.title}`}
                                id={`command-${index}`}
                                role="option"
                                aria-selected={index === active}
                                onMouseEnter={() => setActive(index)}
                                onClick={() => go(index)}
                                className={cn(
                                    'flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 text-sm',
                                    index === active
                                        ? 'bg-accent text-accent-foreground'
                                        : 'text-foreground',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-8 items-center justify-center rounded-lg',
                                        index === active
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground',
                                    )}
                                    aria-hidden="true"
                                >
                                    {item.icon && (
                                        <item.icon className="size-4" />
                                    )}
                                </span>
                                <span className="flex-1 font-medium">
                                    {item.title}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {item.group}
                                </span>
                                {index === active && (
                                    <CornerDownLeft
                                        className="size-4 text-primary"
                                        aria-hidden="true"
                                    />
                                )}
                            </li>
                        ))}
                    </ul>
                </DialogContent>
            </Dialog>
        </>
    );
}
