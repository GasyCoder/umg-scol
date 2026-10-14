import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { article, baseUrl, definitions } from './definitions';
import type {
    EstablishmentOption,
    MentionOption,
    ReferenceKind,
    ReferenceRecord,
} from './definitions';

export type ReferenceFormProps = {
    record: ReferenceRecord | null;
    establishments?: EstablishmentOption[];
    mentions?: MentionOption[];
};
export default function ReferenceForm({
    kind,
    record,
    establishments = [],
    mentions = [],
}: ReferenceFormProps & { kind: ReferenceKind }) {
    const definition = definitions[kind];
    const articleText = article(kind);
    const initial: Record<string, string | boolean> = {
        is_active: record?.is_active ?? true,
    };
    for (const field of definition.fields) {
        const value = record?.[field.key as keyof ReferenceRecord];
        initial[field.key] =
            field.type === 'date' && typeof value === 'string'
                ? value.slice(0, 10)
                : typeof value === 'number' || typeof value === 'string'
                  ? String(value)
                  : field.key === 'sort_order'
                    ? '0'
                    : '';
    }
    const form = useForm(initial);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (record) form.put(`${baseUrl(kind)}/${record.id}`);
        else form.post(baseUrl(kind));
    };
    return (
        <div className="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
            <Head title={definition.title} />
            <Heading
                title={`${record ? 'Modifier' : 'Créer'} ${articleText} ${definition.singular}`}
                description="Référentiel institutionnel de l’Université de Mahajanga."
            />
            <form
                onSubmit={submit}
                className="space-y-5 rounded-lg border bg-card p-5"
            >
                {definition.fields.map((field) => (
                    <div className="grid gap-2" key={field.key}>
                        <Label htmlFor={field.key}>
                            {field.label}
                            {field.required ? ' *' : ''}
                        </Label>
                        {field.type === 'mention' ? (
                            <Select
                                value={String(form.data[field.key]) || 'none'}
                                onValueChange={(value) =>
                                    form.setData(
                                        field.key,
                                        value === 'none' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger id={field.key}>
                                    <SelectValue placeholder="Sans mention" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Sans mention
                                    </SelectItem>
                                    {mentions
                                        .filter(
                                            (option) =>
                                                String(
                                                    option.establishment_id,
                                                ) ===
                                                String(
                                                    form.data.establishment_id,
                                                ),
                                        )
                                        .map((option) => (
                                            <SelectItem
                                                key={option.id}
                                                value={String(option.id)}
                                            >
                                                {option.name}
                                                {!option.is_active
                                                    ? ' (inactive)'
                                                    : ''}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                        ) : field.type === 'establishment' ? (
                            <Select
                                value={String(form.data[field.key])}
                                onValueChange={(value) =>
                                    form.setData((data) => ({
                                        ...data,
                                        [field.key]: value,
                                        ...('mention_id' in data &&
                                        value !== data[field.key]
                                            ? { mention_id: '' }
                                            : {}),
                                    }))
                                }
                                required
                            >
                                <SelectTrigger id={field.key}>
                                    <SelectValue placeholder="Sélectionner un établissement" />
                                </SelectTrigger>
                                <SelectContent>
                                    {establishments.map((option) => (
                                        <SelectItem
                                            key={option.id}
                                            value={String(option.id)}
                                        >
                                            {option.code} — {option.name}
                                            {!option.is_active
                                                ? ' (inactif)'
                                                : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        ) : (
                            <Input
                                id={field.key}
                                type={field.type ?? 'text'}
                                value={String(form.data[field.key])}
                                onChange={(event) =>
                                    form.setData(field.key, event.target.value)
                                }
                                required={field.required}
                                maxLength={field.maxLength}
                                min={field.type === 'number' ? 0 : undefined}
                            />
                        )}
                        <InputError message={form.errors[field.key]} />
                    </div>
                ))}
                <div className="flex items-center gap-2">
                    <Checkbox
                        id="is_active"
                        checked={Boolean(form.data.is_active)}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', checked === true)
                        }
                    />
                    <Label htmlFor="is_active">Actif</Label>
                </div>
                <InputError message={form.errors.is_active} />
                {record?.is_current && (
                    <p className="text-sm text-muted-foreground">
                        La désactivation retirera également le statut d’année
                        courante.
                    </p>
                )}
                <div className="flex gap-3">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Enregistrement…' : 'Enregistrer'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={baseUrl(kind)}>Annuler</Link>
                    </Button>
                </div>
            </form>
        </div>
    );
}
