import { useId } from 'react';
import type { SVGAttributes } from 'react';

/**
 * Neutral "UMG" monogram (not an official university emblem): the letters are cut out of a
 * rounded square, so the mark keeps its contrast on light and dark surfaces with currentColor.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    const mask = `umg-mark-${useId().replace(/:/g, '')}`;
    return (
        <svg
            viewBox="0 0 48 48"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="UMG"
            {...props}
        >
            <mask id={mask}>
                <rect width="48" height="48" rx="11" fill="white" />
                <text
                    x="24"
                    y="29.5"
                    textAnchor="middle"
                    fontFamily="Arial, Helvetica, sans-serif"
                    fontSize="15"
                    fontWeight="700"
                    letterSpacing="0.5"
                    fill="black"
                >
                    UMG
                </text>
            </mask>
            <rect width="48" height="48" rx="11" mask={`url(#${mask})`} />
        </svg>
    );
}
