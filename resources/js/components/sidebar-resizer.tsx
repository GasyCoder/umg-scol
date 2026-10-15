import { GripVertical } from 'lucide-react';
import { useRef, useState } from 'react';
import type { KeyboardEvent, PointerEvent } from 'react';
import { useSidebar } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

export const SIDEBAR_MIN_WIDTH = 224;
export const SIDEBAR_MAX_WIDTH = 400;
export const SIDEBAR_DEFAULT_WIDTH = 256;
/** Releasing the splitter left of this point folds the menu to its icon rail. */
const COLLAPSE_THRESHOLD = 150;

export const clampSidebarWidth = (width: number) =>
    Math.round(Math.min(SIDEBAR_MAX_WIDTH, Math.max(SIDEBAR_MIN_WIDTH, width)));

/**
 * Draggable splitter between the sidebar and the page: drag (mouse, pen or touch),
 * arrow keys, Home/End, or double-click to reset. Desktop only, hidden while the menu is folded.
 */
export function SidebarResizer({
    width,
    onResize,
    onResizeEnd,
    onDraggingChange,
}: {
    width: number;
    onResize: (width: number) => void;
    onResizeEnd: (width: number) => void;
    onDraggingChange: (dragging: boolean) => void;
}) {
    const { state, isMobile, setOpen } = useSidebar();
    const [dragging, setDragging] = useState(false);
    const last = useRef(width);
    const moved = useRef(false);
    const lastDown = useRef(0);

    if (isMobile || state === 'collapsed') return null;

    const toggleDragging = (value: boolean) => {
        setDragging(value);
        onDraggingChange(value);
        document.body.style.cursor = value ? 'col-resize' : '';
        document.body.style.userSelect = value ? 'none' : '';
    };

    const onPointerDown = (event: PointerEvent<HTMLDivElement>) => {
        if (event.button !== 0) return;
        event.preventDefault();
        // Double press resets: handled here because pointer capture can swallow dblclick.
        if (event.timeStamp - lastDown.current < 350) {
            lastDown.current = 0;
            onResizeEnd(SIDEBAR_DEFAULT_WIDTH);
            return;
        }
        lastDown.current = event.timeStamp;
        last.current = width;
        moved.current = false;
        event.currentTarget.setPointerCapture(event.pointerId);
        toggleDragging(true);
    };
    const onPointerMove = (event: PointerEvent<HTMLDivElement>) => {
        if (!dragging) return;
        moved.current = true;
        last.current = clampSidebarWidth(event.clientX);
        onResize(last.current);
    };
    const onPointerUp = (event: PointerEvent<HTMLDivElement>) => {
        if (!dragging) return;
        event.currentTarget.releasePointerCapture(event.pointerId);
        toggleDragging(false);
        // A simple click (no movement) changes nothing.
        if (!moved.current) return;
        onResizeEnd(last.current);
        if (event.clientX < COLLAPSE_THRESHOLD) setOpen(false);
    };
    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        const step = event.shiftKey ? 48 : 16;
        const next =
            event.key === 'ArrowLeft'
                ? width - step
                : event.key === 'ArrowRight'
                  ? width + step
                  : event.key === 'Home'
                    ? SIDEBAR_MIN_WIDTH
                    : event.key === 'End'
                      ? SIDEBAR_MAX_WIDTH
                      : null;
        if (next === null) return;
        event.preventDefault();
        onResizeEnd(clampSidebarWidth(next));
    };

    return (
        <div
            role="separator"
            aria-orientation="vertical"
            aria-label="Redimensionner le menu latéral"
            aria-valuemin={SIDEBAR_MIN_WIDTH}
            aria-valuemax={SIDEBAR_MAX_WIDTH}
            aria-valuenow={width}
            tabIndex={0}
            title="Glisser pour redimensionner · double-clic pour revenir à la largeur par défaut"
            data-dragging={dragging}
            onPointerDown={onPointerDown}
            onPointerMove={onPointerMove}
            onPointerUp={onPointerUp}
            onPointerCancel={onPointerUp}
            onKeyDown={onKeyDown}
            className="group/resizer fixed inset-y-0 left-(--sidebar-width) z-30 hidden w-4 -translate-x-1/2 cursor-col-resize touch-none outline-none md:block"
        >
            {/* Full-height line: subtle at rest, primary while hovered, focused or dragged. */}
            <span
                aria-hidden="true"
                className={cn(
                    'absolute inset-y-0 left-1/2 w-px -translate-x-1/2 bg-transparent transition-all',
                    'group-hover/resizer:w-0.5 group-hover/resizer:bg-primary/70',
                    'group-focus-visible/resizer:w-0.5 group-focus-visible/resizer:bg-primary',
                    dragging && 'w-0.5 bg-primary',
                )}
            />
            {/* Grip: always visible so the splitter reads as draggable. */}
            <span
                aria-hidden="true"
                className={cn(
                    'absolute top-1/2 left-1/2 flex h-10 w-4 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border bg-card text-muted-foreground shadow-card transition-colors',
                    'group-hover/resizer:border-primary/50 group-hover/resizer:text-primary',
                    'group-focus-visible/resizer:border-primary group-focus-visible/resizer:text-primary group-focus-visible/resizer:ring-2 group-focus-visible/resizer:ring-ring',
                    dragging &&
                        'border-primary bg-primary text-primary-foreground',
                )}
            >
                <GripVertical className="size-3" />
            </span>
        </div>
    );
}
