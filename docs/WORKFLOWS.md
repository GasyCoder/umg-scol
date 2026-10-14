# Workflows métier

## Déclaration étudiant

Inscription réelle hors système → établissement confirme → ajout/import → `student` + `student_record` → visible Scolarité Centrale.

## Import

Workflow implémenté dans `/imports` :

1. Choisir établissement/année active. L'établissement est imposé depuis le compte lorsqu'il porte le rôle establishment ; payload étranger refusé 403. Année courante proposée par défaut.
2. Upload XLSX/CSV privé, lecture en blocs et staging uniquement. Une seule feuille, en-têtes en première ligne, sans formules. XLS historique non accepté en V1 ; convertir en XLSX/CSV. Les identifiants doivent être des cellules texte pour préserver les zéros initiaux. Le système ne peut pas restaurer les zéros déjà perdus dans Excel.
3. Mapping déterministe des 17 champs canoniques/alias français, corrigible avant analyse. Nom et niveau obligatoires ; chaque colonne est utilisée au plus une fois. Modèle CSV téléchargeable.
4. Normalisation commune Student, dates Excel 1900/1904, dates ISO ou jj/mm/aaaa. Niveau : code puis nom exact normalisé ; parcours : même résolution uniquement dans l'établissement. Référentiel inconnu/ambigu/inactif rejeté.
5. Analyse des validations, doublons internes et candidats DB. Prévisualisation de 25 lignes paginées, filtres valid/warning/rejected/duplicate et compteurs. CIN/contacts/BAC et données brutes ne sont pas transmis dans la liste.
6. Confirmation explicite : seules les lignes valid sont importables ; les warnings demandent une correction du fichier ou une revue administrative hors pipeline, sans bouton de contournement. Les lignes identiques sont ignorées. Revalidation sous verrou au moment du commit.
7. Résultat : identités créées/complétées, déclarations créées, lignes ignorées ; historique de 20 imports paginés avec relations chargées en une fois. CSV d'erreurs autorisé par imports.export_errors : numéro de ligne, statut, champ, code et message uniquement, sans nom ou autre donnée personnelle.

Permissions existantes imports.view/create/commit/export_errors, Policy serveur et contexte établissement actif. Comptes student et Scolarité Centrale refusés dans cette V1 ; superadmin/developer gardent leurs permissions explicites, sans bypass.

Traitement synchrone V1, **10 Mo, 5 000 lignes physiques de données, 50 colonnes**, expansion XLSX limitée à 100 Mo. Taille/lignes configurables dans `config/student_imports.php`. Lecture et inserts staging par blocs de 200 ; analyse et confirmation par blocs ; aucun chargement intégral des lignes en mémoire. Les clés de doublons/verrouillage sont conservées en mémoire (volume borné), pas le fichier complet. La confirmation reste une transaction globale : les chunks ne sont pas des commits partiels. Test automatisé de 1 001 lignes ; vérifier les temps et limites PHP/proxy sur le matériel cible avant de relever les bornes. Pour des lots plus grands, passer les Actions en Jobs avec suivi d'état ; aucun worker n'est requis en local dans cette phase.

## Navigation des étudiants (portail interne)

`/students` se parcourt en cartes : établissement → niveau → mention → parcours → liste paginée. Chaque carte compte les déclarations actives (et inactives) de l'année choisie, l'année courante par défaut. Un compte établissement commence directement à ses niveaux et ne voit jamais un autre établissement. « Sans mention » regroupe les parcours non rattachés et les déclarations sans parcours ; « Sans parcours », les déclarations sans parcours. À chaque étape : fil d'Ariane cliquable, bouton « Voir toute la sélection en liste », recherche (matricule, nom, prénom, CIN, numéro BAC, téléphone, email) limitée à l'année affichée ; une recherche par URL sans année couvre toutes les années.

## Portail étudiant : compte et rattachement

Implémenté sur `bourse` (`/api/student/*`) :

1. L'étudiant saisit son email → code à 6 chiffres envoyé → vérification → compte `student` créé ou retrouvé, email vérifié. Variante : Google OAuth (email vérifié). Le compte seul ne donne accès à aucune fiche et ne vaut pas inscription.
2. L'étudiant saisit matricule ou numéro d'inscription au BAC + date de naissance.
3. Rattachement automatique si identifiant exact, même naissance, fiche unique et active, non déjà liée, et email officiel de la fiche identique à l'email vérifié du compte.
4. Sinon, si une fiche unique active non liée correspond, une demande `pending` est créée (une seule par compte) ; la réponse reste générique dans tous les cas d'échec.
5. Le personnel autorisé traite la demande dans `/student-links` : approbation (revalidée sous verrou : compte toujours `student` vérifié non lié, fiche active non liée) ou rejet avec motif interne (10 à 500 caractères). Une double approbation est un no-op. Un rattachement automatique ultérieur annule la demande pending.
6. Une fois rattaché, l'étudiant consulte sa fiche et ses déclarations annuelles en lecture seule.

## Vérification étudiant

Identification → consultation → confirmer OU demander correction → établissement accepte/refuse/corrige. Les deux branches sont implémentées.

## Confirmation des informations (implémentée)

La confirmation signifie uniquement : « j'ai vérifié les informations actuellement affichées et je les considère correctes ». Ce n'est ni une inscription, ni une validation par l'établissement, ni une validation de bourse, ni une preuve d'identité, et elle ne modifie ni `Student` ni `StudentRecord`.

1. Page « Mes informations » sur `bourse`, section « Vérification de mes informations » : état `never_confirmed` (jamais confirmé), `confirmed` (« Informations confirmées le … », badge « Confirmées ») ou `stale` (« Vos informations ont changé depuis votre dernière confirmation », badge « À confirmer de nouveau »).
2. Action volontaire : bouton « Je confirme que mes informations sont correctes », puis dialogue « Je confirme avoir vérifié les informations affichées » et bouton « Confirmer mes informations ». Rien n'est confirmé à l'affichage ; pas de case précochée.
3. `POST /api/student/confirmation` n'envoie que le jeton opaque de la version affichée. Le serveur dérive le compte, la fiche, l'année courante et la déclaration, recalcule l'empreinte sous verrou et refuse si la version a changé depuis l'affichage (« Vos informations ont changé depuis leur affichage »). L'étudiant ne confirme donc jamais une version qu'il n'a pas vue.
4. Situation confirmée : année universitaire courante active, puis l'unique déclaration `active` de l'étudiant pour cette année. Aucune année courante, aucune déclaration, déclaration inactive, situation ambiguë ou fiche inactive : refus avec message, rien n'est enregistré.
5. Une demande de correction `pending` bloque la confirmation (« Une ou plusieurs corrections sont encore en attente ») avec un lien « Voir mes demandes ». Après approbation, rejet ou annulation, l'étudiant confirme l'état officiel du moment.
6. Historique conservé : une nouvelle version confirmée ajoute une ligne. Un double clic ou un nouvel essai sur la même version ne crée rien (idempotent, sans second audit).
7. Obsolescence déduite : toute modification d'un champ affiché, de la déclaration courante (niveau, parcours, établissement, statut) ou du libellé affiché d'un référentiel rend la confirmation obsolète. Une nouvelle année courante exige une nouvelle confirmation de la nouvelle situation ; l'historique précédent reste. Les données CIN, non affichées, et l'email de connexion (`User.email`, distinct de l'email de contact `Student.email`) ne sont pas concernés.
8. **La confirmation n'est jamais une condition** de réception, de vérification ou de validation d'un dossier papier de bourse : un étudiant sans compte ou sans confirmation est traité normalement.

Le personnel voit sur la fiche interne « Vérification étudiant » : « À confirmer », « Confirmée le … (année …) » ou « Confirmation devenue obsolète », selon les Policies Student existantes.

## Demande de correction (implémentée)

L'étudiant ne modifie jamais directement ses données officielles :

1. Sur `bourse`, page « Mes informations », bouton « Signaler une erreur » sur chaque information corrigible → nouvelle valeur + message facultatif (500 caractères). `POST /api/student/corrections`.
2. **Une demande = un champ.** Trois erreurs = trois demandes, décidées séparément. Au plus une demande `pending` par étudiant et par champ (garanti en base).
3. Refus immédiat : champ hors liste, valeur invalide selon les règles de saisie administrative, valeur identique après normalisation, compte non rattaché ou fiche inactive.
4. L'étudiant suit ses demandes (« Mes demandes de correction ») et peut annuler une demande `pending` ; il peut ensuite en créer une nouvelle. Pas d'édition d'une demande.
5. Le personnel autorisé ouvre `/corrections` (scol), examine valeur officielle actuelle / valeur proposée / message, puis **approuve** (la fiche est modifiée pour ce seul champ, dans la même transaction) ou **refuse** avec un motif obligatoire (10 à 500 caractères) visible par l'étudiant.
6. Si la valeur officielle a changé depuis la demande, l'approbation est bloquée (conflit) : le personnel réexamine et refuse si nécessaire.

Transitions : `pending → approved | rejected | cancelled` uniquement. Une demande décidée ou annulée n'est jamais retraitée ; une double approbation est un no-op.

Champs corrigibles : nom, prénoms, date et lieu de naissance, CIN (numéro, date et lieu de délivrance), téléphone, email de contact, adresse, numéro/série/année/mention du BAC. Exclus : le matricule (identifiant institutionnel attribué par l'établissement) et toute la déclaration annuelle (année, établissement, niveau, parcours, statut) : l'étudiant est invité à contacter son établissement.

## Bourse (implémentée)

**La demande de bourse reste papier.** Aucun dépôt numérique, aucun upload, aucun formulaire de demande dans le portail étudiant en V1. La plateforme enregistre la réception du dossier papier et son traitement par la Scolarité Centrale.

1. **Campagne** (`/scholarships/campaigns`, `campaigns.manage`) : une campagne appartient à une année universitaire. Statuts `draft → scheduled | open`, `scheduled → open`, `open → closed`, `closed → archived`, sans retour. Ouverture refusée si l'année est inactive. Aucun changement automatique selon les dates en V1 : les dates sont indicatives, l'ouverture et la clôture sont manuelles. Pas de suppression.
2. **Réception** (`/scholarships/reception`, `scholarships.receive`) : uniquement pour une campagne `open`. Le secrétariat choisit la campagne, recherche l'étudiant côté serveur (matricule, nom, prénom, CIN, numéro BAC, téléphone, email ; 2 caractères minimum, 20 résultats maximum), vérifie la fiche synthétique (matricule, nom, naissance, établissement, niveau, parcours, année) puis saisit le numéro DDB et des observations internes.
3. Le serveur impose l'étudiant (actif), la **déclaration annuelle** (l'unique déclaration `active` de l'année de la campagne, avec établissement, niveau et parcours actifs et cohérents), l'acteur et la date. Aucune déclaration, déclaration inactive ou situation ambiguë : refus avec message, aucun dossier créé. **Un dossier par étudiant et par campagne.**
4. **Vérification** (`scholarships.review`) : checklist de contrôles du dossier papier contre la fiche officielle (`conforme`, `anomalie`, `non applicable`, note facultative). Commencer la vérification ou enregistrer un premier contrôle fait passer le dossier `pending → in_review`.
5. **Décision** : `validated` (`scholarships.validate`) exige que tous les contrôles soient conformes ou non applicables, et que la fiche et la déclaration soient toujours actives ; `needs_correction` (`scholarships.request_correction`) et `rejected` (`scholarships.reject`) exigent un motif (10 à 1000 caractères).

Statuts du dossier : `pending` (reçu), `in_review`, `validated`, `needs_correction`, `rejected`. Transitions : `pending → in_review | validated | needs_correction | rejected` ; `in_review → validated | needs_correction | rejected` ; `needs_correction → in_review | validated | rejected`. `validated` et `rejected` sont définitifs en V1 (une réouverture serait une décision métier distincte). Un dossier d'une campagne archivée n'est plus modifiable ; une campagne clôturée bloque la réception, pas le traitement.

`needs_correction` concerne le **dossier papier** (pièce manquante, incohérence à clarifier). Il ne modifie jamais `Student` et ne crée pas de `StudentCorrectionRequest` : si une donnée officielle est fausse, le workflow de correction existant s'applique séparément.

Liste `/scholarships/applications` : pagination serveur (25), recherche (N° dossier, N° DDB, matricule, nom, prénom), filtres campagne, statut, établissement, niveau, période de réception. Export CSV (`scholarships.export`) des mêmes filtres. Historique métier visible sur chaque dossier.

## Historique

Ne jamais écraser un ancien `student_record` lors d'un changement d'année ; créer la déclaration de la nouvelle année.
