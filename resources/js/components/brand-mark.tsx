import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

/** Configured platform logo on a white tile, or the gold UMG monogram when none is set. */
export function BrandMark({
    logoUrl,
    className,
    iconClassName,
}: {
    logoUrl: string | null | undefined;
    className?: string;
    iconClassName?: string;
}) {
    return (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center overflow-hidden rounded-xl',
                logoUrl ? 'bg-white p-1' : 'bg-white/10 ring-1 ring-white/20',
                className,
            )}
        >
            {logoUrl ? (
                <img
                    src={logoUrl}
                    alt=""
                    className="size-full object-contain"
                />
            ) : (
                <AppLogoIcon
                    className={cn(
                        'size-3/4 fill-current text-brand-gold',
                        iconClassName,
                    )}
                    aria-hidden="true"
                />
            )}
        </span>
    );
}
