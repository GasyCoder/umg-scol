import { cn } from '@/lib/utils';

/** Texts of the blank DDB labels, set in Paramètres › Plateforme. */
export type DdbLabelTexts = {
    institution: string;
    heading: string;
    caption: string;
    hint: string;
    footer: string;
    /** Link of the QR code, without its scheme. */
    urlLabel: string;
};

/**
 * One blank DDB label of 99.1 × 57 mm: QR code, printed prefix and a line where the agent
 * writes the number by hand. Shared by the label sheet, the preview of the settings and the
 * reception, which shows the number to copy on the line.
 */
export function DdbLabel({
    texts,
    academicYear,
    prefix,
    suffix,
    number,
    qr,
    className,
}: {
    texts: DdbLabelTexts;
    academicYear: string;
    prefix: string;
    suffix: string;
    /** Number to copy by hand, shown on the line (reception); blank on a printed label. */
    number?: string;
    qr: string;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex h-[57mm] w-[99.1mm] flex-col overflow-hidden border border-dashed border-neutral-400 bg-white px-[4mm] pt-[3mm] pb-[2.5mm] text-black',
                className,
            )}
        >
            <div className="flex min-h-0 flex-1 items-center gap-[4mm]">
                <div className="flex w-[29mm] shrink-0 flex-col items-center gap-[1mm]">
                    <img src={qr} alt="" className="size-[27mm]" />
                    <span className="text-center text-[6pt] leading-tight whitespace-nowrap text-neutral-700">
                        {texts.urlLabel}
                    </span>
                </div>
                <div className="flex min-w-0 flex-1 flex-col gap-[2mm]">
                    <div className="leading-tight">
                        <p className="truncate text-[7.5pt] font-bold uppercase">
                            {texts.institution}
                        </p>
                        <p className="truncate text-[7pt] text-neutral-700">
                            {texts.heading} · {academicYear}
                        </p>
                    </div>
                    <div>
                        <p className="truncate text-[6.5pt] font-semibold tracking-wide text-neutral-600 uppercase">
                            {texts.caption}
                        </p>
                        <p className="flex items-end gap-[1mm] font-mono text-[12pt] leading-none font-bold">
                            {prefix && (
                                <span className="shrink-0">{prefix}</span>
                            )}
                            {/* Line to write the number by hand. */}
                            <span
                                className="flex h-[7mm] min-w-[15mm] flex-1 items-end justify-center border-b border-neutral-800 pb-[0.5mm] text-blue-800"
                                aria-hidden={number ? undefined : true}
                            >
                                {number}
                            </span>
                            {suffix && (
                                <span className="shrink-0">{suffix}</span>
                            )}
                        </p>
                    </div>
                    <p className="text-[6.5pt] leading-tight text-neutral-600">
                        {texts.hint}
                    </p>
                </div>
            </div>
            <p className="truncate border-t border-neutral-300 pt-[1.2mm] text-center text-[7pt] font-semibold">
                {texts.footer}
            </p>
        </div>
    );
}
