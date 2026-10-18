import { GraduationCap } from 'lucide-react';
import { FormField, FormSection } from '@/components/forms/form-kit';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { REPEAT_CODE_CHOICES } from './types';
import type { Option, Options } from './types';

const optionLabel = (option: Option) =>
    `${option.code ? `${option.code} — ` : ''}${option.name}${option.is_active ? '' : ' (inactif)'}`;

/** Annual declaration of a student already enrolled outside the platform (where and at which level). */
export default function RecordFields({
    options,
    data,
    errors,
    setData,
    immutable = false,
    bare = false,
}: {
    options: Options;
    data: Record<string, string>;
    errors: Record<string, string>;
    setData: (key: string, value: string) => void;
    immutable?: boolean;
    bare?: boolean;
}) {
    const programs = options.programs.filter(
        (program) => String(program.establishment_id) === data.establishment_id,
    );
    // Programs grouped by mention; programs without mention come last.
    const groups = Object.values(
        programs.reduce<Record<string, { label: string; items: Option[] }>>(
            (acc, program) => {
                const key = program.mention
                    ? String(program.mention.id)
                    : 'none';
                acc[key] ??= {
                    label: program.mention?.name ?? 'Sans mention',
                    items: [],
                };
                acc[key].items.push(program);
                return acc;
            },
            {},
        ),
    ).sort((a, b) =>
        a.label === 'Sans mention'
            ? 1
            : b.label === 'Sans mention'
              ? -1
              : a.label.localeCompare(b.label, 'fr'),
    );
    const selectedProgram = programs.find(
        (program) => String(program.id) === data.program_id,
    );
    const select = (
        key: string,
        values: Option[],
        placeholder: string,
        disabled = false,
    ) => (
        <Select
            value={data[key] ?? ''}
            onValueChange={(value) => {
                setData(key, value);
                if (key === 'establishment_id') setData('program_id', '');
            }}
            disabled={disabled}
        >
            <SelectTrigger
                id={key}
                className="w-full"
                aria-invalid={errors[key] ? true : undefined}
            >
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {values.map((option) => (
                    <SelectItem
                        key={option.id}
                        value={String(option.id)}
                        disabled={
                            !option.is_active && String(option.id) !== data[key]
                        }
                    >
                        {optionLabel(option)}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    const fields = (
        <>
            <FormField
                id="establishment_id"
                label="Établissement"
                required
                error={errors.establishment_id}
                hint={
                    options.fixedEstablishmentId !== null
                        ? 'Votre établissement.'
                        : immutable
                          ? undefined
                          : 'La situation restera « en attente » jusqu’à ce que la liste de cet établissement la confirme.'
                }
            >
                {select(
                    'establishment_id',
                    options.establishments,
                    'Choisir un établissement',
                    immutable || options.fixedEstablishmentId !== null,
                )}
            </FormField>
            <FormField
                id="academic_year_id"
                label="Année universitaire"
                required
                error={errors.academic_year_id}
                hint="L’année courante est proposée par défaut."
            >
                {select(
                    'academic_year_id',
                    options.academicYears,
                    'Choisir une année',
                    immutable,
                )}
            </FormField>
            <FormField
                id="level_id"
                label="Niveau"
                required
                error={errors.level_id}
            >
                {select('level_id', options.levels, 'Choisir un niveau')}
            </FormField>
            <FormField
                id="program_id"
                label="Parcours"
                error={errors.program_id ?? errors.status}
                hint={
                    !data.establishment_id
                        ? 'Choisissez d’abord l’établissement.'
                        : selectedProgram
                          ? `Mention : ${selectedProgram.mention?.name ?? 'aucune'}`
                          : programs.length === 0
                            ? 'Aucun parcours défini pour cet établissement.'
                            : 'Les parcours sont regroupés par mention.'
                }
            >
                <Select
                    value={data.program_id || 'none'}
                    onValueChange={(value) =>
                        setData('program_id', value === 'none' ? '' : value)
                    }
                    disabled={!data.establishment_id}
                >
                    <SelectTrigger
                        id="program_id"
                        className="w-full"
                        aria-invalid={errors.program_id ? true : undefined}
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">Sans parcours</SelectItem>
                        {groups.map((group) => (
                            <SelectGroup key={group.label}>
                                <SelectLabel>{group.label}</SelectLabel>
                                {group.items.map((program) => (
                                    <SelectItem
                                        key={program.id}
                                        value={String(program.id)}
                                        disabled={
                                            !program.is_active &&
                                            String(program.id) !==
                                                data.program_id
                                        }
                                    >
                                        {optionLabel(program)}
                                    </SelectItem>
                                ))}
                            </SelectGroup>
                        ))}
                    </SelectContent>
                </Select>
            </FormField>
            <FormField
                id="repeat_code"
                label="Code de redoublement"
                required
                error={errors.repeat_code}
                hint="Situation de l’étudiant dans le niveau déclaré."
            >
                <Select
                    value={data.repeat_code || undefined}
                    onValueChange={(value) => setData('repeat_code', value)}
                >
                    <SelectTrigger
                        id="repeat_code"
                        className="w-full"
                        aria-invalid={errors.repeat_code ? true : undefined}
                    >
                        <SelectValue placeholder="Choisir un code" />
                    </SelectTrigger>
                    <SelectContent>
                        {REPEAT_CODE_CHOICES.map((choice) => (
                            <SelectItem key={choice.value} value={choice.value}>
                                {choice.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </FormField>
            <FormField
                id="scholarship_requested_at"
                label="Date de dépôt de la demande de bourse"
                error={errors.scholarship_requested_at}
                hint="À renseigner si l’étudiant a déposé une demande de bourse."
            >
                <Input
                    id="scholarship_requested_at"
                    type="date"
                    value={data.scholarship_requested_at ?? ''}
                    onChange={(event) =>
                        setData('scholarship_requested_at', event.target.value)
                    }
                    min="2000-01-01"
                    max={new Date().toISOString().slice(0, 10)}
                    aria-invalid={
                        errors.scholarship_requested_at ? true : undefined
                    }
                />
            </FormField>
            <FormField
                id="scholarship_request_reference"
                label="Référence demande de bourse"
                hint="Donnée par la plateforme à l’enregistrement, dès qu’une date de dépôt est renseignée (code établissement, année, numéro)."
            >
                <Input
                    id="scholarship_request_reference"
                    value={
                        data.scholarship_request_reference ||
                        (data.scholarship_requested_at
                            ? 'Générée à l’enregistrement'
                            : '—')
                    }
                    readOnly
                    tabIndex={-1}
                    className="bg-muted/40 text-muted-foreground"
                />
            </FormField>
        </>
    );

    // Bare: only the fields, for a page that frames the step itself.
    return bare ? (
        fields
    ) : (
        <FormSection
            icon={GraduationCap}
            title="Situation de l’année"
            description="Établissement, niveau et parcours de l’étudiant, déjà régulièrement inscrit hors plateforme, pour l’année universitaire."
        >
            {fields}
        </FormSection>
    );
}
