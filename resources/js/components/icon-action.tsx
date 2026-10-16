import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

const tones = {
    default: '',
    danger: 'text-danger hover:bg-danger-soft hover:text-danger-foreground',
    success: 'text-success hover:bg-success-soft hover:text-success-foreground',
};

/**
 * Row action shown as an icon only: the label stays in the tooltip and for screen readers.
 * Either a link (href) or a button (onClick).
 */
export function IconAction({
    icon: Icon,
    label,
    href,
    onClick,
    tone = 'default',
    disabled = false,
}: {
    icon: LucideIcon;
    label: string;
    href?: string;
    onClick?: () => void;
    tone?: keyof typeof tones;
    disabled?: boolean;
}) {
    const className = cn('size-8', tones[tone]);
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                {href !== undefined && !disabled ? (
                    <Button
                        variant="ghost"
                        size="icon"
                        className={className}
                        asChild
                    >
                        <Link href={href} aria-label={label}>
                            <Icon className="size-4" aria-hidden="true" />
                        </Link>
                    </Button>
                ) : (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className={className}
                        onClick={onClick}
                        disabled={disabled}
                        aria-label={label}
                    >
                        <Icon className="size-4" aria-hidden="true" />
                    </Button>
                )}
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
