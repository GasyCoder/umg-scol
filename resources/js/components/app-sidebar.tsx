import { Link, usePage } from '@inertiajs/react';
import { BookOpen, ChartColumn, LayoutGrid, ShieldCheck } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';
import { definitions, baseUrl } from '@/components/references/definitions';
import type { ReferenceKind } from '@/components/references/definitions';

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
            icon: BookOpen,
        }));
    const studentItems: NavItem[] = auth.canViewStudents
        ? [{ title: 'Étudiants', href: '/students', icon: BookOpen }]
        : [];
    const scholarshipItems: NavItem[] = [
        ...(auth.scholarships.campaigns
            ? [
                  {
                      title: 'Campagnes',
                      href: '/scholarships/campaigns',
                      icon: BookOpen,
                  },
              ]
            : []),
        ...(auth.scholarships.receive
            ? [
                  {
                      title: 'Réception des dossiers',
                      href: '/scholarships/reception',
                      icon: BookOpen,
                  },
              ]
            : []),
        ...(auth.scholarships.applications
            ? [
                  {
                      title: 'Dossiers',
                      href: '/scholarships/applications',
                      icon: BookOpen,
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
            <SidebarHeader>
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
                                      icon: BookOpen,
                                  },
                              ]
                            : []),
                        ...(auth.canViewCorrections
                            ? [
                                  {
                                      title: 'Corrections',
                                      href: '/corrections',
                                      icon: BookOpen,
                                  },
                              ]
                            : []),
                        ...(auth.canViewImports
                            ? [
                                  {
                                      title: 'Imports',
                                      href: '/imports',
                                      icon: BookOpen,
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
                {references.length > 0 && (
                    <NavMain items={references} label="Référentiels" />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
