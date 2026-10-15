import type { SVGAttributes } from 'react';

/**
 * Flat, theme-aware illustrations drawn with the design tokens (no external images):
 * they follow light/dark mode automatically and stay sober enough for an institutional tool.
 */
type Props = SVGAttributes<SVGElement>;

const c = {
    primary: 'var(--primary)',
    foreground: 'var(--foreground)',
    accent: 'var(--accent)',
    card: 'var(--card)',
    border: 'var(--border)',
    muted: 'var(--muted)',
    mutedFg: 'var(--muted-foreground)',
    gold: 'var(--brand-gold)',
    success: 'var(--success)',
    successSoft: 'var(--success-soft)',
    warning: 'var(--warning)',
    danger: 'var(--danger)',
};

function Frame({ children, ...props }: Props & { children: React.ReactNode }) {
    return (
        <svg
            viewBox="0 0 200 150"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            {...props}
        >
            <ellipse cx="100" cy="132" rx="70" ry="7" fill={c.muted} />
            {children}
        </svg>
    );
}

/** Empty tray with a waiting document: nothing in a list yet. */
export function EmptyInboxIllustration(props: Props) {
    return (
        <Frame {...props}>
            <circle cx="100" cy="70" r="54" fill={c.accent} />
            <rect
                x="70"
                y="26"
                width="60"
                height="74"
                rx="6"
                fill={c.card}
                stroke={c.border}
                strokeWidth="2"
            />
            <rect
                x="80"
                y="38"
                width="28"
                height="5"
                rx="2.5"
                fill={c.primary}
            />
            <rect x="80" y="50" width="40" height="4" rx="2" fill={c.border} />
            <rect x="80" y="60" width="34" height="4" rx="2" fill={c.border} />
            <rect x="80" y="70" width="38" height="4" rx="2" fill={c.border} />
            <path
                d="M50 92h28l6 12h32l6-12h28v24a8 8 0 0 1-8 8H58a8 8 0 0 1-8-8V92Z"
                fill={c.primary}
            />
            <path
                d="M50 92h28l6 12h32l6-12h28"
                stroke={c.card}
                strokeOpacity=".35"
                strokeWidth="2"
            />
            <circle cx="152" cy="34" r="4" fill={c.gold} />
            <circle cx="44" cy="52" r="3" fill={c.primary} opacity=".5" />
            <path
                d="M160 60l3 6 6 3-6 3-3 6-3-6-6-3 6-3 3-6Z"
                fill={c.gold}
                opacity=".8"
            />
        </Frame>
    );
}

/** Magnifier over a sheet: a search or a filter returned nothing. */
export function NoResultsIllustration(props: Props) {
    return (
        <Frame {...props}>
            <circle cx="100" cy="70" r="54" fill={c.accent} />
            <rect
                x="58"
                y="30"
                width="64"
                height="84"
                rx="6"
                fill={c.card}
                stroke={c.border}
                strokeWidth="2"
            />
            <rect
                x="68"
                y="44"
                width="30"
                height="5"
                rx="2.5"
                fill={c.mutedFg}
                opacity=".5"
            />
            <rect x="68" y="56" width="44" height="4" rx="2" fill={c.border} />
            <rect x="68" y="66" width="38" height="4" rx="2" fill={c.border} />
            <rect x="68" y="76" width="42" height="4" rx="2" fill={c.border} />
            <circle
                cx="124"
                cy="84"
                r="20"
                fill={c.card}
                stroke={c.primary}
                strokeWidth="6"
            />
            <path
                d="M138 98l18 18"
                stroke={c.primary}
                strokeWidth="9"
                strokeLinecap="round"
            />
            <path
                d="M117 84h14"
                stroke={c.mutedFg}
                strokeWidth="4"
                strokeLinecap="round"
                opacity=".6"
            />
        </Frame>
    );
}

/** Seal with a check over stacked files: everything is up to date. */
export function AllDoneIllustration(props: Props) {
    return (
        <Frame {...props}>
            <circle cx="100" cy="70" r="54" fill={c.successSoft} />
            <rect
                x="66"
                y="40"
                width="56"
                height="70"
                rx="6"
                fill={c.card}
                stroke={c.border}
                strokeWidth="2"
                transform="rotate(-8 94 75)"
            />
            <rect
                x="76"
                y="34"
                width="56"
                height="70"
                rx="6"
                fill={c.card}
                stroke={c.border}
                strokeWidth="2"
            />
            <rect
                x="86"
                y="48"
                width="26"
                height="5"
                rx="2.5"
                fill={c.border}
            />
            <rect x="86" y="60" width="36" height="4" rx="2" fill={c.border} />
            <rect x="86" y="70" width="30" height="4" rx="2" fill={c.border} />
            <circle cx="130" cy="96" r="20" fill={c.success} />
            <path
                d="M121 96l6 6 12-13"
                stroke={c.card}
                strokeWidth="5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M54 38l2.5 5 5 2.5-5 2.5-2.5 5-2.5-5-5-2.5 5-2.5 2.5-5Z"
                fill={c.gold}
            />
        </Frame>
    );
}

/** Spreadsheet going up into a cloud: student list import. */
export function UploadIllustration(props: Props) {
    return (
        <Frame {...props}>
            <circle cx="100" cy="70" r="54" fill={c.accent} />
            <path
                d="M62 78a20 20 0 0 1 6-39 28 28 0 0 1 53-6 22 22 0 0 1 17 45H62Z"
                fill={c.card}
                stroke={c.border}
                strokeWidth="2"
            />
            <path
                d="M100 72V46m0 0-10 10m10-10 10 10"
                stroke={c.primary}
                strokeWidth="6"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <rect
                x="74"
                y="88"
                width="52"
                height="38"
                rx="5"
                fill={c.card}
                stroke={c.border}
                strokeWidth="2"
            />
            <rect
                x="74"
                y="88"
                width="52"
                height="10"
                rx="5"
                fill={c.success}
            />
            <path
                d="M91 98v28M108 98v28M74 108h52M74 117h52"
                stroke={c.border}
                strokeWidth="2"
            />
        </Frame>
    );
}

/** Folder with a gold seal: scholarship files. */
export function ScholarshipIllustration(props: Props) {
    return (
        <Frame {...props}>
            <circle cx="100" cy="70" r="54" fill={c.accent} />
            <rect
                x="62"
                y="30"
                width="60"
                height="70"
                rx="5"
                fill={c.card}
                stroke={c.border}
                strokeWidth="2"
                transform="rotate(-6 92 65)"
            />
            <path
                d="M48 56a6 6 0 0 1 6-6h28l8 9h56a6 6 0 0 1 6 6v54a6 6 0 0 1-6 6H54a6 6 0 0 1-6-6V56Z"
                fill={c.primary}
            />
            <path
                d="M48 72h104"
                stroke={c.card}
                strokeOpacity=".25"
                strokeWidth="2"
            />
            <circle cx="132" cy="102" r="16" fill={c.gold} />
            <circle
                cx="132"
                cy="102"
                r="10"
                fill="none"
                stroke={c.card}
                strokeWidth="2.5"
                strokeDasharray="3 3"
            />
            <path
                d="M124 116l-4 14 8-4 4 6 2-14M140 116l4 14-8-4"
                fill={c.gold}
            />
            <rect
                x="60"
                y="90"
                width="40"
                height="5"
                rx="2.5"
                fill={c.card}
                opacity=".55"
            />
            <rect
                x="60"
                y="101"
                width="28"
                height="5"
                rx="2.5"
                fill={c.card}
                opacity=".35"
            />
        </Frame>
    );
}

/** Graduation cap over two silhouettes: registered students. */
export function StudentsIllustration(props: Props) {
    return (
        <Frame {...props}>
            <circle cx="100" cy="70" r="54" fill={c.accent} />
            <circle cx="78" cy="70" r="14" fill={c.mutedFg} opacity=".55" />
            <path
                d="M54 126a24 24 0 0 1 48 0Z"
                fill={c.mutedFg}
                opacity=".55"
            />
            <circle cx="118" cy="66" r="16" fill={c.primary} />
            <path d="M90 126a28 28 0 0 1 56 0Z" fill={c.primary} />
            <path d="M118 30l34 13-34 13-34-13 34-13Z" fill={c.foreground} />
            <path
                d="M100 50v10c0 4 8 8 18 8s18-4 18-8V50l-18 6-18-6Z"
                fill={c.foreground}
            />
            <path
                d="M150 44v18"
                stroke={c.gold}
                strokeWidth="3"
                strokeLinecap="round"
            />
            <circle cx="150" cy="65" r="4" fill={c.gold} />
        </Frame>
    );
}

/** Shield with a key hole: protected access, security settings. */
export function SecureAccessIllustration(props: Props) {
    return (
        <Frame {...props}>
            <circle cx="100" cy="70" r="54" fill={c.accent} />
            <path
                d="M100 22l40 14v30c0 28-18 48-40 58-22-10-40-30-40-58V36l40-14Z"
                fill={c.primary}
            />
            <path
                d="M100 34l28 10v22c0 20-12 35-28 43-16-8-28-23-28-43V44l28-10Z"
                fill={c.card}
                opacity=".18"
            />
            <circle cx="100" cy="66" r="10" fill={c.card} />
            <path d="M96 72h8l3 20H93l3-20Z" fill={c.card} />
            <circle cx="148" cy="38" r="4" fill={c.gold} />
            <circle cx="52" cy="100" r="3" fill={c.gold} opacity=".7" />
        </Frame>
    );
}

/**
 * University building with columns and pediment, drawn for dark hero panels:
 * it uses currentColor so the container decides its tint.
 */
export function CampusIllustration(props: Props) {
    return (
        <svg
            viewBox="0 0 320 220"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            {...props}
        >
            <circle cx="276" cy="34" r="18" fill={c.gold} opacity=".9" />
            <path
                d="M160 26l120 52H40l120-52Z"
                fill="currentColor"
                opacity=".9"
            />
            <path
                d="M160 40l84 36H76l84-36Z"
                fill="currentColor"
                opacity=".35"
            />
            <rect
                x="48"
                y="78"
                width="224"
                height="12"
                rx="2"
                fill="currentColor"
                opacity=".9"
            />
            {[66, 102, 138, 174, 210, 246].map((x) => (
                <g key={x}>
                    <rect
                        x={x - 8}
                        y="96"
                        width="16"
                        height="8"
                        rx="1"
                        fill="currentColor"
                        opacity=".7"
                    />
                    <rect
                        x={x - 5}
                        y="104"
                        width="10"
                        height="74"
                        fill="currentColor"
                        opacity=".55"
                    />
                    <rect
                        x={x - 8}
                        y="178"
                        width="16"
                        height="8"
                        rx="1"
                        fill="currentColor"
                        opacity=".7"
                    />
                </g>
            ))}
            <rect
                x="36"
                y="186"
                width="248"
                height="10"
                rx="2"
                fill="currentColor"
                opacity=".9"
            />
            <rect
                x="24"
                y="196"
                width="272"
                height="10"
                rx="2"
                fill="currentColor"
                opacity=".6"
            />
            <circle cx="160" cy="62" r="7" fill={c.gold} />
        </svg>
    );
}
