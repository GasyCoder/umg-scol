import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Options } from './types';
export default function RecordFields({
    options,
    data,
    errors,
    setData,
    immutable = false,
}: {
    options: Options;
    data: Record<string, string>;
    errors: Record<string, string>;
    setData: (key: string, value: string) => void;
    immutable?: boolean;
}) {
    const fields = [
        {
            key: 'establishment_id',
            label: 'Établissement',
            values: options.establishments,
            disabled: immutable || options.fixedEstablishmentId !== null,
        },
        {
            key: 'academic_year_id',
            label: 'Année universitaire',
            values: options.academicYears,
            disabled: immutable,
        },
        {
            key: 'level_id',
            label: 'Niveau',
            values: options.levels,
            disabled: false,
        },
        {
            key: 'program_id',
            label: 'Parcours (facultatif)',
            values: options.programs.filter(
                (program) =>
                    String(program.establishment_id) === data.establishment_id,
            ),
            disabled: false,
        },
    ];
    return (
        <fieldset className="space-y-4 rounded-lg border p-5">
            <legend className="px-2 font-semibold">Déclaration annuelle</legend>
            <p className="text-sm text-muted-foreground">
                L’étudiant est déjà régulièrement inscrit hors plateforme. Cette
                déclaration centralise sa situation administrative.
            </p>
            <div className="grid gap-4 sm:grid-cols-2">
                {fields.map((field) => (
                    <div key={field.key} className="grid gap-2">
                        <Label htmlFor={field.key}>{field.label}</Label>
                        <Select
                            value={data[field.key] || 'none'}
                            onValueChange={(value) => {
                                setData(
                                    field.key,
                                    value === 'none' ? '' : value,
                                );
                                if (field.key === 'establishment_id')
                                    setData('program_id', '');
                            }}
                            disabled={field.disabled}
                        >
                            <SelectTrigger id={field.key}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    {field.key === 'program_id'
                                        ? 'Aucun parcours'
                                        : 'Sélectionner'}
                                </SelectItem>
                                {field.values.map((option) => (
                                    <SelectItem
                                        key={option.id}
                                        value={String(option.id)}
                                        disabled={
                                            !option.is_active &&
                                            String(option.id) !==
                                                data[field.key]
                                        }
                                    >
                                        {option.code ? `${option.code} — ` : ''}
                                        {option.name}
                                        {!option.is_active ? ' (inactif)' : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors[field.key]} />
                    </div>
                ))}
            </div>
            <InputError message={errors.status} />
        </fieldset>
    );
}
