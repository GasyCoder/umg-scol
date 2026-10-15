import { Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type Props = ComponentProps<typeof Link>;

export default function TextLink({
    className = '',
    children,
    ...props
}: Props) {
    return (
        <Link
            className={cn(
                'rounded-sm font-medium text-primary underline decoration-primary/30 underline-offset-4 transition-colors duration-200 ease-out hover:decoration-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                className,
            )}
            {...props}
        >
            {children}
        </Link>
    );
}
