# Cahier des charges — Plateforme Bourse UMG

## Objet

Centraliser et fiabiliser les informations des étudiants déjà régulièrement inscrits dans les établissements de l'Université de Mahajanga afin de faciliter la vérification et le traitement, par la Scolarité Centrale, des demandes papier de bourse nationale.

## Hors périmètre

Inscription/réinscription universitaire, paiement des droits, sélection des étudiants (seule la liste des admis est reprise), notes/examens, paiement de la bourse, gestion pédagogique générale.

## Acteurs

- `establishment`
- `central_scholarship_office`
- `student`
- `superadmin`
- `developer`

## Workflow

1. L'étudiant s'inscrit hors système dans son établissement.
2. L'établissement ajoute ou importe les étudiants régulièrement inscrits.
3. La Scolarité Centrale voit les données centralisées.
4. L'étudiant peut vérifier ses informations et signaler une erreur.
5. Il dépose physiquement son dossier de bourse.
6. Le secrétaire recherche l'étudiant, compare le dossier papier et les données, puis valide, demande correction ou rejette.

## Établissement

Tableau de bord, liste de ses étudiants, ajout/modification, import Excel/CSV, prévisualisation, erreurs/doublons, recherche/filtres, demandes de correction, exports autorisés, historique.

## Étudiant

Consulter ses informations, confirmer leur exactitude, signaler une correction. Aucune modification directe des données officielles.

Portail prévu : `bourse.umg.test:8000`, réservé aux étudiants, utilisant l'API `/api/student/*` du backend Laravel commun. Accès par email officiel déjà présent sur une fiche active, puis OTP (ou Google OAuth soumis à la même restriction), date de naissance et CIN ou renseignement alternatif enregistré. Le rattachement sécurisé à la fiche `Student` est obligatoire avant toute consultation (ADR-086). La création du compte ne constitue jamais une inscription universitaire ; la vérification de l'email seule ne prouve pas le rattachement à la fiche.

Le portail interne `scol.umg.test:8000` utilise Inertia + React + TypeScript et accueille les établissements, la Scolarité Centrale, le superadmin et le developer. Les deux portails, l'authentification étudiante, le rattachement, la demande de correction et la confirmation explicite des informations sont implémentés. La confirmation n'est jamais une condition du traitement papier des bourses.

## Scolarité Centrale

Recherche globale, consultation tous établissements, import de la base PNES des boursiers et des résultats de préinscription des nouveaux bacheliers, saisie d'un étudiant non encore présent dans la liste de son établissement puis validation de sa situation une fois la liste importée, réception d'un dossier papier, numéro DDB, vérification, validation, correction, rejet avec motif, historique, statistiques, exports.

## Superadmin

Établissements, utilisateurs, rôles/permissions, années universitaires, niveaux, parcours, paramètres, audits, statistiques globales.

## Développeur

Support/maintenance avec privilèges élevés. Peut être exclu des listes opérationnelles, mais toujours visible et traçable dans les audits.

## Données étudiant

Champs définis par le fichier modèle des établissements.

Identité : nom, prénoms, sexe, date de naissance, nom et prénoms de la mère, CIN (date et lieu de délivrance), nationalité, année et série du BAC, adresse exacte, téléphone, email (téléphone et email uniques).
Base PNES : code bourse (visible avec la fiche), UIN et numéro de compte (réservés à la Scolarité Centrale).
Situation annuelle : année universitaire, établissement, niveau, mention/parcours, code de redoublement (N, R, T+), date de dépôt et référence de la demande de bourse, statut déclaré, origine (établissement, PNES, Scolarité, préinscription), vérification et statut boursier.
Référentiels associés : domaine de la mention, type de formation du parcours, institution (publique/privée) de l'établissement, année limite du BAC pour la règle de bourse.

## Import

Upload → parsing → validation → détection erreurs/doublons → prévisualisation → confirmation → import transactionnel → résumé.

## Année des dossiers

La réception utilise l’année universitaire courante active, sans campagne. Un seul dossier actif par étudiant et par année ; le déplacement en corbeille libère cette place.

## Demandes de bourse

Statuts : `pending`, `in_review`, `validated`, `needs_correction`, `rejected`. Rejet/correction exige un motif.

## Audit

Tracer au minimum connexions, créations, modifications, désactivations, imports, exports, décisions de bourse, changements de permissions et actions développeur.

## MVP

Authentification, rôles/permissions, établissements, années, niveaux/parcours, étudiants, `student_records`, imports, recherche, traitement des demandes, vérification étudiant, corrections, audit, dashboard, exports.
