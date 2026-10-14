export type ReferenceKind =
    | 'establishments'
    | 'academic-years'
    | 'levels'
    | 'programs';
export type ReferenceRecord = {
    id: number;
    name: string;
    is_active: boolean;
    code?: string | null;
    type?: string | null;
    address?: string | null;
    phone?: string | null;
    email?: string | null;
    starts_at?: string | null;
    ends_at?: string | null;
    is_current?: boolean;
    sort_order?: number;
    establishment_id?: number;
    establishment?: { id: number; code: string; name: string };
};
export type EstablishmentOption = {
    id: number;
    name: string;
    code: string;
    is_active: boolean;
};
export type Field = {
    key: string;
    label: string;
    type?: 'text' | 'email' | 'date' | 'number' | 'establishment';
    required?: boolean;
    maxLength?: number;
};
export const definitions: Record<
    ReferenceKind,
    { title: string; singular: string; permission: string; fields: Field[] }
> = {
    establishments: {
        title: 'Établissements',
        singular: 'établissement',
        permission: 'establishments.manage',
        fields: [
            { key: 'code', label: 'Code', required: true, maxLength: 30 },
            { key: 'name', label: 'Nom', required: true, maxLength: 255 },
            { key: 'type', label: 'Type', maxLength: 100 },
            { key: 'address', label: 'Adresse', maxLength: 2000 },
            { key: 'phone', label: 'Téléphone', maxLength: 30 },
            { key: 'email', label: 'Email', type: 'email', maxLength: 255 },
        ],
    },
    'academic-years': {
        title: 'Années universitaires',
        singular: 'année universitaire',
        permission: 'academic_years.manage',
        fields: [
            {
                key: 'name',
                label: 'Nom (ex. 2026-2027)',
                required: true,
                maxLength: 30,
            },
            { key: 'starts_at', label: 'Date de début', type: 'date' },
            { key: 'ends_at', label: 'Date de fin', type: 'date' },
        ],
    },
    levels: {
        title: 'Niveaux',
        singular: 'niveau',
        permission: 'levels.manage',
        fields: [
            { key: 'code', label: 'Code', required: true, maxLength: 30 },
            { key: 'name', label: 'Nom', required: true, maxLength: 255 },
            {
                key: 'sort_order',
                label: 'Ordre d’affichage',
                type: 'number',
                required: true,
            },
        ],
    },
    programs: {
        title: 'Parcours',
        singular: 'parcours',
        permission: 'programs.manage',
        fields: [
            {
                key: 'establishment_id',
                label: 'Établissement',
                type: 'establishment',
                required: true,
            },
            { key: 'code', label: 'Code', maxLength: 30 },
            { key: 'name', label: 'Nom', required: true, maxLength: 255 },
        ],
    },
};
export const baseUrl = (kind: ReferenceKind) => `/references/${kind}`;
