import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { CSSProperties, ReactNode } from 'react';
import {
    clampSidebarWidth,
    SIDEBAR_DEFAULT_WIDTH,
    SidebarResizer,
} from '@/components/sidebar-resizer';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    children: ReactNode;
    variant?: AppVariant;
};

const saveWidth = (width: number) => {
    document.cookie = `sidebar_width=${width};path=/;max-age=${60 * 60 * 24 * 365};SameSite=Lax`;
};

export function AppShell({ children, variant = 'sidebar' }: Props) {
    const { sidebarOpen, sidebarWidth } = usePage().props;
    // Read from the cookie on the server too, so the first render already has the chosen width.
    const [width, setWidth] = useState(() =>
        clampSidebarWidth(sidebarWidth ?? SIDEBAR_DEFAULT_WIDTH),
    );
    const [resizing, setResizing] = useState(false);

    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full flex-col">{children}</div>
        );
    }

    return (
        <SidebarProvider
            defaultOpen={sidebarOpen}
            style={{ '--sidebar-width': `${width}px` } as CSSProperties}
            data-resizing={resizing || undefined}
        >
            {children}
            <SidebarResizer
                width={width}
                onResize={setWidth}
                onResizeEnd={(value) => {
                    setWidth(value);
                    saveWidth(value);
                }}
                onDraggingChange={setResizing}
            />
        </SidebarProvider>
    );
}
