export type Student = {
    id: number;
    /** Public identifier used in every URL. */
    uuid: string;
    student_number: string | null;
    last_name: string;
    first_names: string | null;
    birth_date: string | null;
    birth_place: string | null;
    cin: string | null;
    cin_issued_at: string | null;
    cin_issued_place: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    bac_registration_number: string | null;
    bac_series: string | null;
    bac_year: number | null;
    bac_mention: string | null;
    is_active: boolean;
};
export type Option = {
    id: number;
    name: string;
    code?: string;
    is_active: boolean;
    establishment_id?: number;
    is_current?: boolean;
    mention?: { id: number; name: string } | null;
};
export type Options = {
    establishments: Option[];
    academicYears: Option[];
    levels: Option[];
    programs: Option[];
    fixedEstablishmentId: number | null;
};
export type AnnualRecord = {
    id: number;
    uuid: string;
    student_id: number;
    establishment_id: number;
    academic_year_id: number;
    level_id: number;
    program_id: number | null;
    status: 'active' | 'inactive';
    declared_at: string;
    student?: Pick<
        Student,
        | 'id'
        | 'uuid'
        | 'student_number'
        | 'last_name'
        | 'first_names'
        | 'is_active'
    >;
    establishment?: Option;
    academic_year?: Option;
    level?: Option;
    program?: Option | null;
    declared_by?: { id: number; name: string };
    canUpdate?: boolean;
};
export const identitySections = [
    {
        title: 'Identité',
        fields: [
            { key: 'student_number', label: 'Matricule', max: 100 },
            { key: 'last_name', label: 'Nom', required: true, max: 150 },
            { key: 'first_names', label: 'Prénoms', max: 150 },
            { key: 'birth_date', label: 'Date de naissance', type: 'date' },
            { key: 'birth_place', label: 'Lieu de naissance', max: 255 },
        ],
    },
    {
        title: 'Contact',
        fields: [
            { key: 'phone', label: 'Téléphone', max: 30 },
            { key: 'email', label: 'Email', type: 'email', max: 255 },
            { key: 'address', label: 'Adresse', max: 2000 },
        ],
    },
    {
        title: 'Identification',
        fields: [
            { key: 'cin', label: 'CIN', max: 100 },
            {
                key: 'cin_issued_at',
                label: 'Date de délivrance CIN',
                type: 'date',
            },
            {
                key: 'cin_issued_place',
                label: 'Lieu de délivrance CIN',
                max: 255,
            },
        ],
    },
    {
        title: 'Baccalauréat',
        fields: [
            {
                key: 'bac_registration_number',
                label: 'Numéro d’inscription BAC',
                max: 100,
            },
            { key: 'bac_series', label: 'Série BAC', max: 50 },
            { key: 'bac_year', label: 'Année BAC', type: 'number' },
            { key: 'bac_mention', label: 'Mention BAC', max: 100 },
        ],
    },
] satisfies {
    title: string;
    fields: {
        key: keyof Student;
        label: string;
        type?: string;
        max?: number;
        required?: boolean;
    }[];
}[];
