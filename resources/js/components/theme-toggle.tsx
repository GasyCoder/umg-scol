import { Check, Monitor, Moon, Sun } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import type { Appearance } from '@/hooks/use-appearance';

const options: { value: Appearance; label: string; icon: LucideIcon }[] = [
    { value: 'light', label: 'Clair', icon: Sun },
    { value: 'dark', label: 'Sombre', icon: Moon },
    { value: 'system', label: 'Système', icon: Monitor },
];

/** Light / dark / system switch in the top bar; the choice is kept in a cookie and localStorage. */
export function ThemeToggle({ className }: { className?: string }) {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className={cn(
                        'relative size-10 rounded-full border bg-card text-muted-foreground shadow-xs hover:border-primary/40 hover:bg-accent hover:text-primary',
                        className,
                    )}
                    aria-label="Changer le thème (clair, sombre ou système)"
                >
                    <Sun className="size-5 scale-100 rotate-0 transition-all dark:scale-0 dark:-rotate-90" />
                    <Moon className="absolute size-5 scale-0 rotate-90 transition-all dark:scale-100 dark:rotate-0" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44 rounded-xl p-1.5">
                <DropdownMenuLabel className="text-xs font-semibold text-muted-foreground">
                    Apparence
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {options.map(({ value, label, icon: Icon }) => (
                    <DropdownMenuItem
                        key={value}
                        onSelect={() => updateAppearance(value)}
                        className="gap-2.5 rounded-lg"
                    >
                        <Icon className="size-4" />
                        {label}
                        {appearance === value && (
                            <Check className="ml-auto size-4 text-primary" />
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
