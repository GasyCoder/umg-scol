import type {
    Auth,
    Branding,
    NotificationFeed,
    PendingTasks,
} from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            /** Sidebar width in px chosen with the splitter (cookie). */
            sidebarWidth: number;
            tasks: PendingTasks;
            notifications: NotificationFeed;
            branding: Branding;
            [key: string]: unknown;
        };
    }
}
