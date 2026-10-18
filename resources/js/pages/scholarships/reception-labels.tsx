import { Head, router } from '@inertiajs/react';
import { Printer, Tags } from 'lucide-react';
import { useState } from 'react';
import { createPortal } from 'react-dom';
import { EmptyState } from '@/components/empty-state';
import { PageBody, PageHeader } from '@/components/page-header';
import { DdbLabel } from '@/components/scholarships/ddb-label';
import type { DdbLabelTexts } from '@/components/scholarships/ddb-label';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Year = { id: number; name: string; is_current: boolean };
type LabelParts = { prefix: string; suffix: string; digits: number };

const LABELS_PER_SHEET = 10;

/**
 * A4 sheet of 2 × 5 labels of 99.1 × 57 mm (standard self-adhesive sheets, L7173 type):
 * on plain paper the dashed borders are cut lines. Only the sheets are printed.
 */
const printStyles = `
.label-sheet { width: 210mm; height: 297mm; padding: 6mm 4.65mm; display: grid; grid-template-columns: repeat(2, 99.1mm); grid-auto-rows: 57mm; column-gap: 2.5mm; box-sizing: border-box; background: #fff; color: #000; }
@media screen { #labels-print { display: none; } }
@media print {
    @page { size: A4 portrait; margin: 0; }
    body > *:not(#labels-print) { display: none !important; }
    .label-sheet { break-after: page; }
    .label-sheet:last-child { break-after: auto; }
}`;

export default function ReceptionLabels({
    years,
    yearId,
    label,
    institution,
    texts,
    qr,
    maxSheets,
}: {
    /** Active academic years, the current one first. */
    years: Year[];
    yearId: number | null;
    label: LabelParts | null;
    institution: string;
    /** Texts set in Paramètres › Plateforme. */
    texts: Omit<DdbLabelTexts, 'institution'> & { url: string };
    qr: string;
    maxSheets: number;
}) {
    const [sheets, setSheets] = useState('1');
    const count = Math.min(
        Math.max(Number.parseInt(sheets, 10) || 1, 1),
        maxSheets,
    );
    const year = years.find((y) => y.id === yearId) ?? null;

    const sheet = (key?: number) =>
        label && year ? (
            <LabelSheet
                key={key}
                label={label}
                year={year}
                texts={{ ...texts, institution }}
                qr={qr}
            />
        ) : null;

    return (
        <PageBody>
            <Head title="Fiche d’étiquettes DDB" />
            <style>{printStyles}</style>

            <PageHeader
                icon={Tags}
                title="Fiche d’étiquettes DDB"
                description={`${LABELS_PER_SHEET} étiquettes par feuille A4, avec le préfixe du numéro DDB déjà imprimé : il reste à écrire le numéro au stylo. Le QR code renvoie vers ${texts.urlLabel}.`}
            />

            {year === null || label === null ? (
                <div className="surface">
                    <EmptyState
                        illustration="scholarship"
                        title="Aucune année universitaire active."
                        description="Le préfixe des étiquettes dépend de l’année universitaire. Activez une année dans Années universitaires."
                    />
                </div>
            ) : (
                <>
                    <section className="surface flex flex-col gap-4 p-4 sm:flex-row sm:items-end">
                        {years.length > 1 && (
                            <div className="grid min-w-0 gap-1.5 sm:w-64">
                                <Label htmlFor="labels-year">
                                    Année universitaire
                                </Label>
                                <Select
                                    value={String(year.id)}
                                    onValueChange={(value) =>
                                        router.get(
                                            '/scholarships/reception/labels',
                                            { academic_year_id: value },
                                            {
                                                preserveState: true,
                                                replace: true,
                                            },
                                        )
                                    }
                                >
                                    <SelectTrigger id="labels-year">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {years.map((y) => (
                                            <SelectItem
                                                key={y.id}
                                                value={String(y.id)}
                                            >
                                                {y.name}
                                                {y.is_current && ' (courante)'}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                        <div className="grid gap-1.5 sm:w-40">
                            <Label htmlFor="labels-sheets">
                                Nombre de feuilles
                            </Label>
                            <Input
                                id="labels-sheets"
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={maxSheets}
                                value={sheets}
                                onChange={(event) =>
                                    setSheets(event.target.value)
                                }
                                onBlur={() => setSheets(String(count))}
                            />
                        </div>
                        <Button
                            size="lg"
                            onClick={() => window.print()}
                            autoFocus
                        >
                            <Printer aria-hidden="true" />
                            Imprimer {count * LABELS_PER_SHEET} étiquettes
                        </Button>
                        <p className="text-sm text-muted-foreground sm:ml-auto sm:max-w-xs sm:text-right">
                            Imprimez à 100 % (taille réelle), sans marges
                            ajoutées par le navigateur.
                        </p>
                    </section>

                    <div className="space-y-2">
                        <h2 className="text-sm font-semibold text-muted-foreground">
                            Aperçu d’une feuille
                        </h2>
                        <div className="overflow-x-auto rounded-xl border bg-muted/40 p-4">
                            <div className="mx-auto w-fit shadow-sm">
                                {sheet()}
                            </div>
                        </div>
                    </div>

                    {typeof document !== 'undefined' &&
                        createPortal(
                            <div id="labels-print">
                                {Array.from({ length: count }, (_, index) =>
                                    sheet(index),
                                )}
                            </div>,
                            document.body,
                        )}
                </>
            )}
        </PageBody>
    );
}

function LabelSheet({
    label,
    year,
    texts,
    qr,
}: {
    label: LabelParts;
    year: Year;
    texts: DdbLabelTexts;
    qr: string;
}) {
    return (
        <div className="label-sheet">
            {Array.from({ length: LABELS_PER_SHEET }, (_, index) => (
                <DdbLabel
                    key={index}
                    texts={texts}
                    academicYear={year.name}
                    prefix={label.prefix}
                    suffix={label.suffix}
                    qr={qr}
                />
            ))}
        </div>
    );
}

ReceptionLabels.layout = {
    breadcrumbs: [
        { title: 'Réception des dossiers', href: '/scholarships/reception' },
        { title: 'Fiche d’étiquettes', href: '/scholarships/reception/labels' },
    ],
};
