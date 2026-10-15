import type { ReactNode } from 'react';
import {
    AllDoneIllustration,
    EmptyInboxIllustration,
    NoResultsIllustration,
    ScholarshipIllustration,
    SecureAccessIllustration,
    StudentsIllustration,
    UploadIllustration,
} from '@/components/illustrations';
import { cn } from '@/lib/utils';

const illustrations = {
    inbox: EmptyInboxIllustration,
    search: NoResultsIllustration,
    done: AllDoneIllustration,
    upload: UploadIllustration,
    scholarship: ScholarshipIllustration,
    students: StudentsIllustration,
    secure: SecureAccessIllustration,
};

export type EmptyStateIllustration = keyof typeof illustrations;

/** Illustrated empty, no-result or "all done" message with an optional next action. */
export function EmptyState({
    illustration = 'inbox',
    title,
    description,
    action,
    compact = false,
    className,
}: {
    illustration?: EmptyStateIllustration;
    title: string;
    description?: ReactNode;
    action?: ReactNode;
    compact?: boolean;
    className?: string;
}) {
    const Illustration = illustrations[illustration];
    return (
        <div
            role="status"
            className={cn(
                'flex flex-col items-center justify-center text-center',
                compact ? 'gap-2 px-4 py-6' : 'gap-3 px-6 py-12',
                className,
            )}
        >
            <Illustration className={compact ? 'h-24 w-auto' : 'h-36 w-auto'} />
            <div className="max-w-md space-y-1">
                <p className="font-heading text-base font-bold text-foreground">
                    {title}
                </p>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {action && <div className="pt-1">{action}</div>}
        </div>
    );
}
