import { identitySections } from '@/components/students/types';
import type { Student } from '@/components/students/types';
import { FormField } from '@/components/forms/form-kit';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

export type ReceptionRecord = {
    establishment: string;
    level: string;
    program: string | null;
    status: string;
    source: string;
    source_label: string;
    verification: string;
    verification_label: string;
    is_scholarship_holder: boolean;
};

export type ReceptionStudent = Omit<Student, 'sex'> & {
    sex: string | null;
    cin_is_standard: boolean;
    missing: string[];
    records: (ReceptionRecord & {
        academic_year: string;
        /** Situation of the academic year being received. */
        is_reception_year: boolean;
    })[];
    applications: {
        uuid: string;
        reference: string;
        academic_year: string;
        status: string;
        status_label: string;
        received_at: string;
    }[];
    canUpdate: boolean;
    /** File of the academic year being received, when already received: one request per year. */
    yearApplication: { uuid: string; reference: string } | null;
    /** Why no file can be registered this year, null when it can. */
    blockReason: string | null;
};

/** The academic year whose paper files are received (the current one). */
export type ReceptionYear = { id: number; name: string };
export type FieldKey = Exclude<keyof Student, 'id' | 'uuid' | 'is_active'>;
export type ReceptionField = {
    key: FieldKey;
    label: string;
    type?: string;
    max?: number;
    required?: boolean;
    choices?: { value: string; label: string }[];
};

/** Identity sections of the student file, with the PNES identifiers for the central office. */
export const sectionsFor = (student: ReceptionStudent) =>
    identitySections.map((section) =>
        section.title === 'Identification'
            ? {
                  ...section,
                  fields: [
                      ...section.fields,
                      {
                          key: 'scholarship_code',
                          label: 'Code bourse',
                          max: 30,
                      },
                      ...('uin' in student
                          ? [
                                { key: 'uin', label: 'UIN', max: 30 },
                                {
                                    key: 'bank_account',
                                    label: 'Numéro de compte',
                                    max: 34,
                                },
                            ]
                          : []),
                  ] as ReceptionField[],
              }
            : { ...section, fields: section.fields as ReceptionField[] },
    );

export const missingLabels: Record<string, string> = {
    ...Object.fromEntries(
        identitySections.flatMap((section) =>
            section.fields.map((field) => [field.key, field.label]),
        ),
    ),
    scholarship_code: 'Code bourse',
    uin: 'UIN',
    bank_account: 'Numéro de compte',
};

/** Fields the server requires on the identity (StudentInput); the others are optional. */
export const requiredFields = new Set<string>(
    identitySections.flatMap((section) =>
        section.fields
            .filter((field) => 'required' in field && field.required)
            .map((field) => field.key as string),
    ),
);

/** Empty fields split between required (to complete) and optional (completed if provided). */
export const splitMissing = (keys: string[]) => ({
    required: keys.filter((key) => requiredFields.has(key)),
    optional: keys.filter((key) => !requiredFields.has(key)),
});

const valueOf = (student: ReceptionStudent, key: FieldKey) => {
    const value = student[key];
    return value === null || value === undefined ? '' : String(value);
};

export const valuesOf = (student: ReceptionStudent) =>
    Object.fromEntries(
        sectionsFor(student)
            .flatMap((section) => section.fields)
            .map((field) => [field.key, valueOf(student, field.key)]),
    );

export function IdentityInput({
    field,
    value,
    error,
    missing,
    readOnly,
    inputRef,
    onChange,
}: {
    field: ReceptionField;
    value: string;
    error?: string;
    missing: boolean;
    readOnly: boolean;
    inputRef?: (element: HTMLElement | null) => void;
    onChange: (value: string) => void;
}) {
    const id = `reception-${field.key}`;
    const common = {
        id,
        disabled: readOnly,
        'aria-invalid': error ? true : undefined,
    };
    const warn = missing && 'border-warning bg-warning-soft/40';
    return (
        <FormField
            id={id}
            label={field.label}
            required={field.required}
            error={error}
            hint={missing ? 'À compléter' : undefined}
            wide={field.key === 'address'}
        >
            {field.choices ? (
                <Select value={value} onValueChange={onChange}>
                    <SelectTrigger
                        {...common}
                        ref={inputRef}
                        className={cn('w-full', warn)}
                    >
                        <SelectValue placeholder="Choisir" />
                    </SelectTrigger>
                    <SelectContent>
                        {field.choices.map((choice) => (
                            <SelectItem key={choice.value} value={choice.value}>
                                {choice.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            ) : field.key === 'address' ? (
                <textarea
                    {...common}
                    ref={inputRef}
                    className={cn(
                        'min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-70 aria-invalid:border-destructive',
                        warn,
                    )}
                    maxLength={field.max}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                />
            ) : (
                <Input
                    {...common}
                    ref={inputRef}
                    type={field.type ?? 'text'}
                    maxLength={field.max}
                    className={cn(warn)}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                />
            )}
        </FormField>
    );
}
