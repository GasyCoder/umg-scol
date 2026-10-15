import { usePage } from '@inertiajs/react';
import {
    BookMarked,
    Building2,
    CalendarDays,
    CalendarRange,
    ChartColumn,
    FilePenLine,
    FileUp,
    FolderOpen,
    Inbox,
    KeyRound,
    LayoutGrid,
    Layers,
    Route,
    ShieldCheck,
    Trash2,
    UserCheck,
    UserCog,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { baseUrl, definitions } from '@/components/references/definitions';
import type { ReferenceKind } from '@/components/references/definitions';
import { dashboard } from '@/routes';
import type { NavGroup, NavItem } from '@/types';

const referenceIcons: Record<ReferenceKind, LucideIcon> = {
    establishments: Building2,
    'academic-years': CalendarDays,
    levels: Layers,
    mentions: BookMarked,
    programs: Route,
};

/** Pending task keys (PendingTasks.php) counted on each menu entry. */
const taskEntries: Record<string, string> = {
    corrections: '/corrections',
    student_links: '/student-links',
    applications_pending: '/scholarships/applications',
    applications_in_review: '/scholarships/applications',
    imports_ready: '/imports',
};

/**
 * Single source of the internal menu, shared by the sidebar and the page search.
 * Entries mirror the server-side abilities shared by HandleInertiaRequests; the server
 * still authorizes every page.
 */
export function useNavigation(): NavGroup[] {
    const { auth, tasks } = usePage().props;
    const badges: Record<string, number> = {};
    for (const task of tasks?.items ?? []) {
        const href = taskEntries[task.key];
        if (href) badges[href] = (badges[href] ?? 0) + task.count;
    }
    const item = (title: string, href: string, icon: LucideIcon): NavItem => ({
        title,
        href,
        icon,
        badge: badges[href],
    });
    const when = (condition: boolean, entry: NavItem) =>
        condition ? [entry] : [];

    const groups: NavGroup[] = [
        {
            label: 'Général',
            items: [
                {
                    title: 'Tableau de bord',
                    href: dashboard(),
                    icon: LayoutGrid,
                },
            ],
        },
        {
            label: 'Étudiants',
            items: [
                ...when(
                    auth.canViewStudents,
                    item('Étudiants déclarés', '/students', Users),
                ),
                ...when(
                    auth.canViewImports,
                    item('Imports de listes', '/imports', FileUp),
                ),
                ...when(
                    auth.permissions.includes('student_links.review'),
                    item('Rattachements', '/student-links', UserCheck),
                ),
                ...when(
                    auth.canViewCorrections,
                    item('Corrections', '/corrections', FilePenLine),
                ),
            ],
        },
        {
            label: 'Bourses',
            items: [
                ...when(
                    auth.scholarships.campaigns,
                    item('Campagnes', '/scholarships/campaigns', CalendarRange),
                ),
                ...when(
                    auth.scholarships.receive,
                    item(
                        'Réception des dossiers',
                        '/scholarships/reception',
                        Inbox,
                    ),
                ),
                ...when(
                    auth.scholarships.applications,
                    item(
                        'Dossiers de bourse',
                        '/scholarships/applications',
                        FolderOpen,
                    ),
                ),
            ],
        },
        {
            label: 'Pilotage',
            items: [
                ...when(
                    auth.canViewReports,
                    item('Rapports', '/reports', ChartColumn),
                ),
                ...when(
                    auth.canViewAudits,
                    item('Journal d’audit', '/audits', ShieldCheck),
                ),
            ],
        },
        {
            label: 'Référentiels',
            items: (Object.keys(definitions) as ReferenceKind[])
                .filter((kind) =>
                    auth.permissions.includes(definitions[kind].permission),
                )
                .map((kind) =>
                    item(
                        definitions[kind].title,
                        baseUrl(kind),
                        referenceIcons[kind],
                    ),
                ),
        },
        {
            label: 'Administration',
            items: [
                ...when(
                    auth.canManageUsers,
                    item('Utilisateurs', '/users', UserCog),
                ),
                ...when(
                    auth.canManagePermissions,
                    item('Permissions', '/permissions', KeyRound),
                ),
                ...when(
                    auth.canManageTrash,
                    item('Corbeille', '/trash', Trash2),
                ),
            ],
        },
    ];

    return groups.filter((group) => group.items.length > 0);
}
