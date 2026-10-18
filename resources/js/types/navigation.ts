import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /** False: reachable from the page search only (e.g. notifications, opened from the header bell). */
    inSidebar?: boolean;
};

export type NavGroup = {
    label: string;
    items: NavItem[];
};
