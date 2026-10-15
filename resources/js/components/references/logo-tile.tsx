import { Building2 } from 'lucide-react';
import { cn } from '@/lib/utils';

/** Logo image, or the sigle initials on a tinted tile when there is no logo. */
export function LogoTile({
    src,
    code,
    className,
}: {
    src: string | null | undefined;
    code: string | null | undefined;
    className?: string;
}) {
    return src ? (
        <img
            src={src}
            alt=""
            className={cn(
                'shrink-0 border bg-white object-contain p-1.5',
                className,
            )}
        />
    ) : (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center bg-accent font-heading font-extrabold text-primary',
                className,
            )}
            aria-hidden="true"
        >
            {(code || '').slice(0, 3) || <Building2 className="size-1/3" />}
        </span>
    );
}
