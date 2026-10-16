import type { Tone } from '@/components/tone-badge';

export type Page<T> = {
    data: T[];
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};
export type ImportStatus =
    | 'uploaded'
    | 'analyzing'
    | 'ready'
    | 'committing'
    | 'completed'
    | 'failed'
    | 'cancelled';
export type ImportItem = {
    id: number;
    uuid: string;
    original_filename: string;
    status: ImportStatus;
    total_rows: number;
    valid_rows: number;
    warning_rows: number;
    rejected_rows: number;
    duplicate_rows: number;
    created_rows: number;
    updated_rows: number;
    declared_rows: number;
    skipped_rows: number;
    created_at: string;
    failure_reason: string | null;
    headers: string[];
    mapping: Record<string, number>;
    /** Referentials created in the establishment: planned at analysis, actual once created is true. */
    reference_changes: {
        levels?: string[];
        domains: string[];
        mentions: string[];
        programs: string[];
        created: boolean;
    } | null;
    /** Main causes of the rows that cannot be imported, with the referential values of the file. */
    issue_summary:
        | {
              field: string;
              code: string;
              message: string;
              severity: 'error' | 'warning';
              rows: number;
              values: string[];
          }[]
        | null;
    /** Null for an import covering several establishments. */
    establishment: { name: string } | null;
    academic_year: { name: string };
    user: { name: string };
};
export type RowStatus = 'valid' | 'warning' | 'rejected' | 'duplicate';
export type ImportRow = {
    id: number;
    row_number: number;
    status: RowStatus;
    errors: { field: string; code: string; message: string }[] | null;
    warnings: { field: string; code: string; message: string }[] | null;
    /** Information only (references to create), never blocking. */
    notices?: string[];
    matched_student_id: number | null;
    resolution: string | null;
    data: Record<string, string | null>;
};
export const statuses: Record<ImportStatus | RowStatus, string> = {
    uploaded: 'Fichier reçu',
    analyzing: 'Analyse en cours',
    ready: 'Prêt à confirmer',
    committing: 'Import en cours',
    completed: 'Terminé',
    failed: 'Échec',
    cancelled: 'Annulé',
    valid: 'Valide',
    warning: 'Avertissement',
    rejected: 'Rejetée',
    duplicate: 'Doublon',
};

/** Row status → tone: valid rows go through, the others are set aside (colour repeats the written label). */
export const rowStatusTones: Record<RowStatus, Tone> = {
    valid: 'success',
    warning: 'warning',
    rejected: 'danger',
    duplicate: 'info',
};
export const rowStatusOrder: RowStatus[] = [
    'valid',
    'warning',
    'rejected',
    'duplicate',
];
