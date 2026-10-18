import {
    ArrowLeft,
    Ban,
    CircleCheck,
    Clock,
    FilePenLine,
    Mail,
    Undo2,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import { EmptyState } from '@/components/empty-state';
import { ToneBadge, toneFor } from '@/components/tone-badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader } from '@/components/ui/card';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import { SectionTitle } from './section-title';

export type Correction = {
    uuid: string;
    field: string;
    field_label: string;
    proposed_value: string;
    /** Sex, nationality or address saved at once, without review. */
    direct: boolean;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    status_label: string;
    student_message: string | null;
    decision_reason: string | null;
    created_at: string;
    reviewed_at: string | null;
};

/** An email of the scholarship office about the record: field names and message, never values. */
export type OfficeMessage = {
    id: number;
    at: string;
    message: string | null;
    fields: string[];
};

type Filter = 'all' | 'pending' | 'done';

const date = (value: string) =>
    new Date(value).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

/** Icon and colour of a request's state: never the colour alone, the badge says it in words. */
const states: Record<
    Correction['status'] | 'direct',
    { icon: LucideIcon; tone: string }
> = {
    pending: { icon: Clock, tone: 'text-warning' },
    approved: { icon: CircleCheck, tone: 'text-success' },
    direct: { icon: CircleCheck, tone: 'text-success' },
    rejected: { icon: XCircle, tone: 'text-danger' },
    cancelled: { icon: Ban, tone: 'text-muted-foreground' },
};

function Section({
    icon,
    title,
    description,
    action,
    children,
}: {
    icon: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <Card className="gap-0 overflow-hidden py-0">
            <CardHeader className="gap-3 border-b px-5 py-4 sm:px-6">
                <div className="space-y-1">
                    <SectionTitle icon={icon}>{title}</SectionTitle>
                    {description && (
                        <CardDescription>{description}</CardDescription>
                    )}
                </div>
                {action}
            </CardHeader>
            {children}
        </Card>
    );
}

/**
 * « Mes demandes » (ADR-089): every correction the student asked for or saved, and the messages of the
 * scholarship office, on a page of their own instead of the bottom of the record.
 */
export function RequestsPage({
    corrections,
    messages,
    busy,
    onBack,
    onCancel,
    formatValue,
}: {
    corrections: Correction[];
    messages: OfficeMessage[] | null;
    busy: boolean;
    onBack: () => void;
    onCancel: (uuid: string) => void;
    formatValue: (field: string, value: string) => string;
}) {
    const [filter, setFilter] = useState<Filter>('all');
    const pending = corrections.filter((item) => item.status === 'pending');
    const shown = corrections.filter((item) =>
        filter === 'all'
            ? true
            : filter === 'pending'
              ? item.status === 'pending'
              : item.status !== 'pending',
    );
    const counts: Record<Filter, number> = {
        all: corrections.length,
        pending: pending.length,
        done: corrections.length - pending.length,
    };

    return (
        <div className="space-y-8">
            <div className="space-y-3">
                <Button
                    variant="ghost"
                    className="-ml-3 h-9 text-muted-foreground"
                    onClick={onBack}
                >
                    <ArrowLeft aria-hidden="true" />
                    Ma fiche
                </Button>
                <div>
                    <h1 className="font-heading text-2xl font-bold tracking-tight sm:text-3xl">
                        Mes demandes
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {pending.length > 0
                            ? `${pending.length} demande${pending.length > 1 ? 's' : ''} en attente de validation`
                            : 'Aucune demande en attente'}
                    </p>
                </div>
            </div>

            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <Section
                    icon={FilePenLine}
                    title="Corrections"
                    description="Le sexe, la nationalité et l’adresse sont enregistrés tout de suite ; les autres informations après validation par la scolarité."
                    action={
                        corrections.length > 0 && (
                            <ToggleGroup
                                type="single"
                                value={filter}
                                onValueChange={(value) =>
                                    value && setFilter(value as Filter)
                                }
                                className="grid w-full grid-cols-3 gap-1 rounded-lg bg-muted p-1 sm:w-auto sm:min-w-80"
                                aria-label="Filtrer les demandes"
                            >
                                {(
                                    [
                                        ['all', 'Toutes'],
                                        ['pending', 'En attente'],
                                        ['done', 'Traitées'],
                                    ] as [Filter, string][]
                                ).map(([value, label]) => (
                                    <ToggleGroupItem
                                        key={value}
                                        value={value}
                                        className="h-8 gap-1.5 rounded-md px-2 text-xs text-muted-foreground hover:bg-background/60 hover:text-foreground data-[state=on]:bg-background data-[state=on]:text-foreground data-[state=on]:shadow-sm"
                                    >
                                        {label}
                                        <span className="text-muted-foreground tabular-nums">
                                            {counts[value]}
                                        </span>
                                    </ToggleGroupItem>
                                ))}
                            </ToggleGroup>
                        )
                    }
                >
                    {corrections.length === 0 ? (
                        <EmptyState
                            compact
                            illustration="inbox"
                            title="Aucune demande pour le moment."
                            description="Touchez le crayon à côté d’une information de votre fiche."
                        />
                    ) : shown.length === 0 ? (
                        <p className="px-5 py-8 text-center text-sm text-muted-foreground sm:px-6">
                            {filter === 'pending'
                                ? 'Aucune demande en attente.'
                                : 'Aucune demande traitée.'}
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {shown.map((item) => {
                                const state =
                                    states[
                                        item.direct ? 'direct' : item.status
                                    ];

                                return (
                                    <li
                                        key={item.uuid}
                                        className="flex gap-3 px-5 py-4 sm:px-6"
                                    >
                                        <state.icon
                                            className={cn(
                                                'mt-0.5 size-5 shrink-0',
                                                state.tone,
                                            )}
                                            aria-hidden="true"
                                        />
                                        <div className="min-w-0 flex-1 space-y-1.5">
                                            <div className="flex items-start justify-between gap-3">
                                                <p className="font-medium text-foreground">
                                                    {item.field_label}
                                                </p>
                                                <ToneBadge
                                                    tone={
                                                        item.direct
                                                            ? 'success'
                                                            : toneFor(
                                                                  item.status,
                                                              )
                                                    }
                                                    className="shrink-0"
                                                >
                                                    {item.status_label}
                                                </ToneBadge>
                                            </div>
                                            <p className="text-sm break-words text-muted-foreground">
                                                {item.direct
                                                    ? 'Nouvelle valeur'
                                                    : 'Proposé'}{' '}
                                                :{' '}
                                                <span className="text-foreground">
                                                    {formatValue(
                                                        item.field,
                                                        item.proposed_value,
                                                    )}
                                                </span>
                                            </p>
                                            {item.decision_reason && (
                                                <p className="rounded-md bg-muted px-3 py-2 text-sm text-foreground">
                                                    <span className="text-muted-foreground">
                                                        Motif :
                                                    </span>{' '}
                                                    {item.decision_reason}
                                                </p>
                                            )}
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <p className="text-xs text-muted-foreground">
                                                    {item.direct
                                                        ? 'Enregistrée'
                                                        : 'Envoyée'}{' '}
                                                    le {date(item.created_at)}
                                                    {item.reviewed_at &&
                                                        !item.direct &&
                                                        ` · décision le ${date(item.reviewed_at)}`}
                                                </p>
                                                {item.status === 'pending' && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-8 text-danger hover:bg-danger-soft hover:text-danger-foreground"
                                                        disabled={busy}
                                                        onClick={() =>
                                                            onCancel(item.uuid)
                                                        }
                                                    >
                                                        <Undo2 aria-hidden="true" />
                                                        Annuler la demande
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </Section>

                <Section icon={Mail} title="Messages de la scolarité">
                    {messages === null ? (
                        <p className="px-5 py-6 text-sm text-muted-foreground sm:px-6">
                            Chargement…
                        </p>
                    ) : messages.length === 0 ? (
                        <p className="px-5 py-6 text-sm text-muted-foreground sm:px-6">
                            Aucun message.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {messages.map((message) => (
                                <li
                                    key={message.id}
                                    className="space-y-2 px-5 py-4 sm:px-6"
                                >
                                    <p className="text-xs text-muted-foreground">
                                        {date(message.at)}
                                    </p>
                                    {message.fields.length > 0 && (
                                        <div className="flex flex-wrap gap-1.5">
                                            {message.fields.map((field) => (
                                                <ToneBadge
                                                    key={field}
                                                    tone="warning"
                                                >
                                                    {field}
                                                </ToneBadge>
                                            ))}
                                        </div>
                                    )}
                                    {message.message && (
                                        <p className="text-sm whitespace-pre-line text-foreground">
                                            {message.message}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>
            </div>
        </div>
    );
}
