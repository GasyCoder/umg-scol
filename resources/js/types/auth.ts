export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    permissions: string[];
    canViewStudents: boolean;
    canViewImports: boolean;
    canViewCorrections: boolean;
    canViewReports: boolean;
    canManagePermissions: boolean;
    canManageTrash: boolean;
    /** Label of the account's role, shown in the top bar. */
    roleLabel?: string | null;
    canViewAudits: boolean;
    scholarships: {
        campaigns: boolean;
        receive: boolean;
        applications: boolean;
    };
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};

export type PendingTask = {
    key: string;
    label: string;
    description: string;
    count: number;
    href: string;
};

export type PendingTasks = {
    total: number;
    items: PendingTask[];
};
