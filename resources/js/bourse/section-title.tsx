import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { CardTitle } from '@/components/ui/card';

/** Title of a portal card: a small icon, no tile, then the words. Shared by every section. */
export function SectionTitle({
    icon: Icon,
    id,
    children,
}: {
    icon: LucideIcon;
    id?: string;
    children: ReactNode;
}) {
    return (
        <CardTitle
            id={id}
            role="heading"
            aria-level={2}
            className="flex items-center gap-2 text-base"
        >
            <Icon className="size-4 shrink-0 text-primary" aria-hidden="true" />
            {children}
        </CardTitle>
    );
}
