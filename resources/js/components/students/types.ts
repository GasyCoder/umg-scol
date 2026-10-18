export type StudentSex = 'M' | 'F';
export type RepeatCode = 'N' | 'P' | 'R' | 'T+';
export type Student = {
    id: number;
    /** Public identifier used in every URL. */
    uuid: string;
    last_name: string;
    first_names: string | null;
    sex: StudentSex | null;
    birth_date: string | null;
    mother_name: string | null;
    cin: string | null;
    cin_issued_at: string | null;
    cin_issued_place: string | null;
    /** Code bourse of the PNES base, shown everywhere. */
    scholarship_code: string | null;
    /** UIN and bank account: only sent to the central office (absent otherwise). */
    uin?: string | null;
    bank_account?: string | null;
    nationality: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    bac_series: string | null;
    bac_year: number | null;
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
/** Indicative scholarship rule of the establishment file, computed by the server per declaration. */
export type Eligibility = {
    status: 'eligible' | 'not_eligible' | 'undetermined';
    rate: string;
    reasons: string[];
};
export type AnnualRecord = {
    id: number;
    uuid: string;
    student_id: number;
    establishment_id: number;
    academic_year_id: number;
    level_id: number;
    program_id: number | null;
    repeat_code: RepeatCode | null;
    scholarship_requested_at: string | null;
    scholarship_request_reference: string | null;
    status: RecordStatus;
    source: SituationSource;
    verification: SituationVerification;
    /** Scholarship holder of the year (PNES base). */
    is_scholarship_holder: boolean;
    preinscription_reference?: string | null;
    declared_at: string;
    student?: Pick<
        Student,
        | 'id'
        | 'uuid'
        | 'last_name'
        | 'first_names'
        | 'sex'
        | 'birth_date'
        | 'is_active'
    >;
    establishment?: Option;
    academic_year?: Option & { scholarship_max_bac_year?: number | null };
    level?: Option;
    program?: Option | null;
    declared_by?: { id: number; name: string };
    eligibility?: Eligibility;
    /** Deactivated together with the identity; restored when the identity is reactivated. */
    inactive_with_identity?: boolean;
    canUpdate?: boolean;
    canVerify?: boolean;
};

/** Where the situation of the year comes from (mirrors App\Enums\SituationSource). */
export type SituationSource =
    | 'establishment'
    | 'pnes'
    | 'central'
    | 'preinscription';
/** Status of a situation (mirrors App\Enums\StudentRecordStatus). */
export type RecordStatus = 'active' | 'inactive' | 'pending';
export const RECORD_STATUS_LABELS: Record<RecordStatus, string> = {
    active: 'Active',
    inactive: 'Inactive',
    pending: 'En attente',
};
export const SOURCE_LABELS: Record<SituationSource, string> = {
    establishment: 'Liste de l’établissement',
    pnes: 'Base PNES (boursiers)',
    central: 'Saisie de la Scolarité',
    preinscription: 'Préinscription (nouveau bachelier)',
};
/** Proof of the enrolment of the year (mirrors App\Enums\SituationVerification). */
export type SituationVerification = 'verified' | 'pending' | 'matched';
export const VERIFICATION_LABELS: Record<SituationVerification, string> = {
    verified: 'Vérifiée',
    pending: 'Pas encore dans la liste de l’établissement',
    matched: 'Trouvée dans la liste — à valider',
};
/** Short wording for badges in lists. */
export const VERIFICATION_SHORT: Record<SituationVerification, string> = {
    verified: 'Vérifiée',
    pending: 'En attente',
    matched: 'À valider',
};

/** Closed lists of the official establishment template (mirrors App\Support\StudentReferenceLists). */
export const NATIONALITIES = [
    'Malagasy',
    'Comorien',
    'Africain',
    'Européen',
    'Asiatique',
    'Mauricien',
    'Autres',
] as const;
export const BAC_SERIES = [
    'A1',
    'A2',
    'C',
    'D',
    'L',
    'S',
    'OSE',
    'Tech. Génie civil',
    'Tech. Industriel',
    'Tech. Tertiaire',
    'Tech. Agricole',
    'Technologique',
    'Autres',
] as const;
export const SEX_LABELS: Record<StudentSex, string> = {
    M: 'Masculin',
    F: 'Féminin',
};
export const REPEAT_CODE_LABELS: Record<RepeatCode, string> = {
    N: 'N (Nouveau)',
    P: 'P (Passant)',
    R: 'R (Redoublant)',
    'T+': 'T+ (Triplant ou plus)',
};
export type SelectChoice = { value: string; label: string };
const choices = (values: readonly string[]): SelectChoice[] =>
    values.map((value) => ({ value, label: value }));
export const SEX_CHOICES: SelectChoice[] = Object.entries(SEX_LABELS).map(
    ([value, label]) => ({ value, label }),
);
export const REPEAT_CODE_CHOICES: SelectChoice[] = Object.entries(
    REPEAT_CODE_LABELS,
).map(([value, label]) => ({ value, label }));
export const NATIONALITY_CHOICES = choices(NATIONALITIES);
export const BAC_SERIES_CHOICES = choices(BAC_SERIES);

export const identitySections = [
    {
        title: 'Identité',
        fields: [
            { key: 'last_name', label: 'Nom', required: true, max: 150 },
            { key: 'first_names', label: 'Prénoms', max: 150 },
            {
                key: 'sex',
                label: 'Sexe',
                type: 'select',
                required: true,
                choices: SEX_CHOICES,
            },
            {
                key: 'birth_date',
                label: 'Date de naissance',
                type: 'date',
                required: true,
            },
            {
                key: 'mother_name',
                label: 'Nom et prénoms de la mère',
                max: 255,
            },
            {
                key: 'nationality',
                label: 'Nationalité',
                type: 'select',
                required: true,
                choices: NATIONALITY_CHOICES,
            },
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
        title: 'Contact',
        fields: [
            { key: 'phone', label: 'Téléphone', max: 30 },
            { key: 'email', label: 'Email', type: 'email', max: 255 },
            { key: 'address', label: 'Adresse exacte', max: 2000 },
        ],
    },
    {
        title: 'Baccalauréat',
        fields: [
            { key: 'bac_year', label: 'Année BAC', type: 'number' },
            {
                key: 'bac_series',
                label: 'Série BAC',
                type: 'select',
                choices: BAC_SERIES_CHOICES,
            },
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
        choices?: SelectChoice[];
    }[];
}[];
