import { Link, usePage } from '@inertiajs/react';
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
    LayoutGrid,
    Layers,
    KeyRound,
    Trash2,
    Route,
    ShieldCheck,
    UserCheck,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';
import { definitions, baseUrl } from '@/components/references/definitions';
import type { ReferenceKind } from '@/components/references/definitions';

const referenceIcons: Record<ReferenceKind, LucideIcon> = {
    establishments: Building2,
    'academic-years': CalendarDays,
    levels: Layers,
    mentions: BookMarked,
    programs: Route,
};

const mainNavItems: NavItem[] = [
    {
        title: 'Tableau de bord',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    const references: NavItem[] = (Object.keys(definitions) as ReferenceKind[])
        .filter((kind) =>
            auth.permissions.includes(definitions[kind].permission),
        )
        .map((kind) => ({
            title: definitions[kind].title,
            href: baseUrl(kind),
            icon: referenceIcons[kind],
        }));
    const studentItems: NavItem[] = auth.canViewStudents
        ? [{ title: 'Étudiants', href: '/students', icon: Users }]
        : [];
    const scholarshipItems: NavItem[] = [
        ...(auth.scholarships.campaigns
            ? [
                  {
                      title: 'Campagnes',
                      href: '/scholarships/campaigns',
                      icon: CalendarRange,
                  },
              ]
            : []),
        ...(auth.scholarships.receive
            ? [
                  {
                      title: 'Réception des dossiers',
                      href: '/scholarships/reception',
                      icon: Inbox,
                  },
              ]
            : []),
        ...(auth.scholarships.applications
            ? [
                  {
                      title: 'Dossiers',
                      href: '/scholarships/applications',
                      icon: FolderOpen,
                  },
              ]
            : []),
    ];
    const steeringItems: NavItem[] = [
        ...(auth.canViewReports
            ? [{ title: 'Rapports', href: '/reports', icon: ChartColumn }]
            : []),
        ...(auth.canViewAudits
            ? [{ title: 'Audit', href: '/audits', icon: ShieldCheck }]
            : []),
    ];
    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader className="h-16 justify-center border-b px-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain
                    items={[
                        ...mainNavItems,
                        ...studentItems,
                        ...(auth.permissions.includes('student_links.review')
                            ? [
                                  {
                                      title: 'Rattachements étudiants',
                                      href: '/student-links',
                                      icon: UserCheck,
                                  },
                              ]
                            : []),
                        ...(auth.canViewCorrections
                            ? [
                                  {
                                      title: 'Corrections',
                                      href: '/corrections',
                                      icon: FilePenLine,
                                  },
                              ]
                            : []),
                        ...(auth.canViewImports
                            ? [
                                  {
                                      title: 'Imports',
                                      href: '/imports',
                                      icon: FileUp,
                                  },
                              ]
                            : []),
                    ]}
                />
                {scholarshipItems.length > 0 && (
                    <NavMain items={scholarshipItems} label="Bourses" />
                )}
                {steeringItems.length > 0 && (
                    <NavMain items={steeringItems} label="Pilotage" />
                )}
                {(auth.canManagePermissions || auth.canManageTrash) && (
                    <NavMain
                        label="Administration"
                        items={[
                            ...(auth.canManagePermissions
                                ? [
                                      {
                                          title: 'Permissions',
                                          href: '/permissions',
                                          icon: KeyRound,
                                      },
                                  ]
                                : []),
                            ...(auth.canManageTrash
                                ? [
                                      {
                                          title: 'Corbeille',
                                          href: '/trash',
                                          icon: Trash2,
                                      },
                                  ]
                                : []),
                        ]}
                    />
                )}
                {references.length > 0 && (
                    <NavMain items={references} label="Référentiels" />
                )}
            </SidebarContent>
        </Sidebar>
    );
}
