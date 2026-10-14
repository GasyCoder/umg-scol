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
    establishment: { name: string };
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
