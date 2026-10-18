import { usePage } from '@inertiajs/react';
import {
    Bell,
    BookMarked,
    Building2,
    CalendarDays,
    ChartColumn,
    FilePenLine,
    FileUp,
    FolderOpen,
    Inbox,
    KeyRound,
    LayoutGrid,
    Layers,
    Library,
    Route,
    ShieldCheck,
    Tags,
    Trash2,
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
    domains: Library,
    mentions: BookMarked,
    programs: Route,
};

/**
 * Single source of the internal menu, shared by the sidebar and the page search.
 * Entries mirror the server-side abilities shared by HandleInertiaRequests; the server
 * still authorizes every page. Work waiting (PendingTasks.php) is shown by the header bell only.
 */
export function useNavigation(): NavGroup[] {
    const { auth } = usePage().props;
    const item = (title: string, href: string, icon: LucideIcon): NavItem => ({
        title,
        href,
        icon,
    });
    const when = (condition: boolean, entry: NavItem) =>
        condition ? [entry] : [];

    const groups: NavGroup[] = [
        {
            label: 'Général',
            items: [
                ...when(auth.canViewDashboard, {
                    title: 'Tableau de bord',
                    href: dashboard(),
                    icon: LayoutGrid,
                }),
                // Opened from the bell of the header, not from the menu.
                ...when(auth.canViewNotifications, {
                    ...item('Notifications', '/notifications', Bell),
                    inSidebar: false,
                }),
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
                    auth.canViewCorrections,
                    item('Corrections', '/corrections', FilePenLine),
                ),
            ],
        },
        {
            label: 'Bourses',
            items: [
                ...when(
                    auth.scholarships.receive,
                    item(
                        'Réception des dossiers',
                        '/scholarships/reception',
                        Inbox,
                    ),
                ),
                ...when(
                    auth.scholarships.labels,
                    item(
                        'Étiquettes DDB',
                        '/scholarships/reception/labels',
                        Tags,
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
