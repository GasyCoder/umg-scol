import {
    BookMarked,
    Building2,
    CalendarDays,
    Layers,
    Route,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReferenceKind } from './definitions';

/** Same icons as the sidebar entries (hooks/use-navigation.ts). */
export const referenceIcons: Record<ReferenceKind, LucideIcon> = {
    establishments: Building2,
    'academic-years': CalendarDays,
    levels: Layers,
    mentions: BookMarked,
    programs: Route,
};

/** One-line description of a single record, shown on the create/edit form. */
export const recordDescriptions: Record<ReferenceKind, string> = {
    establishments: 'Faculté, école ou institut de l’Université de Mahajanga.',
    'academic-years':
        'Période universitaire ; l’année courante se définit depuis la liste.',
    levels: 'Niveau d’études (L1, L2, M1…), commun à tous les établissements.',
    mentions:
        'Mention d’un établissement (LMD) ; elle regroupe plusieurs parcours.',
    programs:
        'Parcours d’un établissement, rattaché si possible à une mention du même établissement.',
};

/** Page description of each reference list. */
export const listDescriptions: Record<ReferenceKind, string> = {
    establishments:
        'Facultés, écoles et instituts de l’Université de Mahajanga.',
    'academic-years':
        'Périodes universitaires ; une seule année est courante à la fois.',
    levels: 'Niveaux d’études (L1, L2, M1…), communs à tous les établissements.',
    mentions:
        'Mentions LMD de chaque établissement ; elles regroupent les parcours.',
    programs:
        'Parcours de chaque établissement, rattachés si possible à une mention.',
};

/** Date-only value (YYYY-MM-DD…) to dd/mm/yyyy, without any timezone shift. */
export const formatDay = (value?: string | null) => {
    if (!value) return null;
    const [year, month, day] = value.slice(0, 10).split('-');
    return year && month && day ? `${day}/${month}/${year}` : value;
};
