import type { Tone } from '@/components/tone-badge';
import type { ImportKind } from './types';

/** Wording of each kind of file: short label, one plain sentence, page intro and tips. */
export const kindTexts: Record<
    ImportKind,
    { label: string; sentence: string; intro: string; tips: string[] }
> = {
    establishment: {
        label: 'Liste de l’établissement',
        sentence:
            'Les étudiants inscrits, déclarés par l’établissement au format du fichier officiel.',
        intro: 'Déclarez en une fois des étudiants déjà régulièrement inscrits. Rien n’est enregistré avant votre confirmation.',
        tips: [
            'Téléchargez le modèle Excel : colonnes A à Z du fichier établissement, listes déroulantes (sexe, nationalité, série, code, niveau, mention, parcours…) et formules BOURSIER/TAUX.',
            'Le fichier officiel ou un export de la page Étudiants se déposent tels quels : leurs colonnes sont reconnues automatiquement.',
            'La ligne de repères « [ 1 ] [ 2 ]… » et le bloc final « Arrêté au nombre de… » sont ignorés.',
            'Les colonnes BOURSIER et TAUX DE BOURSE (formules) ne sont pas importées : la plateforme applique elle-même la règle.',
            'Un téléphone saisi comme nombre retrouve son 0 initial ; un CIN autre que 12 chiffres est accepté mais signalé.',
            'Le fichier déclare des étudiants déjà inscrits : aucune inscription n’est effectuée.',
        ],
    },
    pnes: {
        label: 'Base PNES (boursiers)',
        sentence:
            'La base nationale des boursiers : une ligne par étudiant, avec code bourse, UIN et numéro de compte.',
        intro: 'Chargez la base PNES des boursiers, tous établissements confondus. Rien n’est enregistré avant votre confirmation.',
        tips: [
            'Téléchargez le modèle PNES : Code Bourse, UIN et Numéro de compte en tête, puis l’identité et le cursus.',
            'Chaque ligne indique son établissement dans la colonne « Institution Code ».',
            'Une situation déjà déclarée par l’établissement est retrouvée et marquée boursier PNES.',
            'Dans le modèle, toutes les cellules sont en texte : UIN, numéros de compte et CIN gardent tous leurs chiffres.',
            'Le fichier ne vaut pas inscription : aucune inscription n’est effectuée.',
        ],
    },
    preinscription: {
        label: 'Préinscription (nouveaux bacheliers)',
        sentence:
            'Les nouveaux bacheliers retenus après le Bacc ; chaque ligne indique son établissement.',
        intro: 'Chargez la liste des nouveaux bacheliers retenus, tous établissements confondus. Rien n’est enregistré avant votre confirmation.',
        tips: [
            'Téléchargez le modèle de préinscription : une ligne par bachelier retenu, avec son numéro de dossier.',
            'Chaque ligne indique son établissement dans la colonne « Institution Code ».',
            'Sans autre précision dans le fichier, les bacheliers sont pris en première année, comme nouveaux étudiants.',
            'Le fichier ne vaut pas inscription : l’inscription se fait ensuite dans l’établissement.',
        ],
    },
};

export const templateHref = (kind: ImportKind): string =>
    kind === 'establishment'
        ? '/imports/template'
        : `/imports/template?kind=${kind}`;

/** Who sends each kind of file, and its colour in the history (with its label, never colour alone). */
export const kindMeta: Record<
    ImportKind,
    { short: string; tone: Tone; from: string; effect: string }
> = {
    establishment: {
        short: 'Liste établissement',
        tone: 'neutral',
        from: 'Envoyée par l’établissement',
        effect: 'Déclare les situations de l’année et confirme les saisies de la Scolarité.',
    },
    pnes: {
        short: 'Base PNES',
        tone: 'success',
        from: 'Base nationale, chargée par la Scolarité',
        effect: 'Retrouve ou crée chaque boursier et marque sa situation « Boursier PNES ».',
    },
    preinscription: {
        short: 'Préinscription',
        tone: 'info',
        from: 'Sélection après le Bacc, chargée par la Scolarité',
        effect: 'Crée les nouveaux bacheliers admis en première année.',
    },
};
