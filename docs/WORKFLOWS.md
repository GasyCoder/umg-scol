# Workflows métier

## Déclaration étudiant

Inscription réelle hors système → établissement confirme → ajout/import → `student` + `student_record` → visible Scolarité Centrale.

## Import

Workflow implémenté dans `/imports` :

1. Choisir établissement/année active. L'établissement est imposé depuis le compte lorsqu'il porte le rôle establishment ; payload étranger refusé 403. Année courante proposée par défaut. Un acteur global peut choisir « Tous les établissements (selon le fichier) » : l'import n'a pas d'établissement (`student_imports.establishment_id` NULL) et chaque ligne désigne le sien par la colonne « Code établissement » (« Institution Code », sigle exact) ou, à défaut, « École / Institut » (nom, mêmes mots). Établissement introuvable ou inactif : ligne rejetée. Mentions, parcours et références de demande sont résolus et créés dans l'établissement de la ligne. Pour un import d'un seul établissement, un code établissement différent rejette la ligne.
2. Upload XLSX/CSV privé, lecture en blocs et staging uniquement. Une seule feuille, en-têtes en première ligne. Le **fichier modèle des établissements** est accepté tel quel : la ligne de légende `[ 1 ]`, `[ 2 ]`… sous les en-têtes et le bloc de totaux du bas (à partir de « Arrêté au nombre de ») sont ignorés ; les colonnes BOURSIER et TAUX DE BOURSE (formules) ne sont jamais importées. Une formule dans une colonne importée rejette la ligne. XLS historique non accepté en V1 ; convertir en XLSX/CSV. Les cellules numériques (CIN, téléphone, référence) sont relues avec tous leurs chiffres ; le zéro initial d'un téléphone à 9 chiffres est restauré.
3. Mapping déterministe des 25 colonnes (24 du modèle + « Code établissement » ; en-têtes « Prenom », « Nom de la Mere », « Numero CIN », « Numero de Telephone », « E-mail », « Institution Code » reconnus ; « Institution » à côté d'« Institution Code » désigne l'établissement et non publique/privée). Sexe et nationalité ne sont obligatoires que si le fichier a la colonne. Code de redoublement : N, P (Passant, aussi « PASSANT »), R, T+ ; P a le taux 1 comme N. Mapping corrigible avant analyse (variantes d'en-têtes comprises, y compris « Date delivrabce »). Nom, date de naissance, code de redoublement et niveau obligatoires ; chaque colonne est utilisée au plus une fois. Modèle XLSX téléchargeable (`/imports/template`, `EstablishmentTemplateWorkbook`) : colonnes A à Z, en-têtes et couleurs du fichier établissement, listes déroulantes (sexe, nationalité, série, code de redoublement, institution, type de formation, niveau, et pour l'établissement du compte : domaine, mention, parcours, École / Institut) tirées d'une feuille masquée « Listes », formules BOURSIER/TAUX reprenant la règle avec l'année limite de l'année courante, CIN/téléphone/référence en cellules texte. Seule la feuille « Listes » est admise en plus de la feuille de données ; les lignes ne contenant que les formules du modèle sont ignorées.
4. Normalisation commune Student, dates Excel 1900/1904, dates ISO ou j/m/aaaa. Niveau : code puis nom exact normalisé ; mention : même résolution dans l'établissement ; parcours : dans la mention indiquée (un même nom, « Tronc Commun », peut exister dans plusieurs mentions), sinon dans l'établissement. Mention sans parcours rejetée. **Mention ou parcours absent de l'établissement : créé automatiquement à la confirmation**, dans l'établissement de l'import uniquement (`CreateImportReferences`, même transaction, création auditée comme une saisie manuelle) ; la nouvelle mention reçoit le domaine du fichier (existant, ou créé s'il est écrit « (CODE) Nom »), le nouveau parcours le type de formation du fichier. La prévisualisation l'annonce sur chaque ligne (information non bloquante) et dans un encadré récapitulatif, puis l'import terminé liste ce qui a été créé. Une mention/un parcours inactif ou ambigu n'est jamais dupliqué : ligne rejetée. Niveau : un niveau LMD standard absent (L1, L2, L3, M1, M2, écrit en code ou « Licence 3 ») est créé à la confirmation ; tout autre niveau inconnu rejette la ligne (à créer par l'administrateur). Les créations ne sont annoncées que sur les lignes importables. Un encadré « Pourquoi des lignes ne sont pas importables » résume les causes (nombre de lignes, valeurs de référentiel du fichier, jamais de données personnelles, action à mener), enregistré à l'analyse dans `student_imports.issue_summary`. Colonnes descriptives contrôlées : « École / Institut » doit désigner l'établissement de l'import (nom, sigle ou « Nom (SIGLE) ») ; Institution, Domaine (« (ALSH) Arts, Lettres… », code ou nom) et Type de formation doivent concorder avec l'établissement, la mention et le parcours lorsque ceux-ci les renseignent. CIN de 12 chiffres = CIN standard ; une autre valeur est acceptée et signalée. Téléphone, email et référence de demande déjà utilisés par une autre identité (base ou fichier) : ligne rejetée.
5. Analyse des validations, doublons internes et candidats DB. Prévisualisation de 25 lignes paginées, filtres valid/warning/rejected/duplicate et compteurs. CIN/contacts/BAC et données brutes ne sont pas transmis dans la liste.
6. Confirmation explicite : seules les lignes valid sont importables ; les warnings demandent une correction du fichier ou une revue administrative hors pipeline, sans bouton de contournement. Les lignes identiques sont ignorées. Revalidation sous verrou au moment du commit.
7. Résultat : identités créées/complétées, déclarations créées, lignes ignorées ; historique de 20 imports paginés avec relations chargées en une fois. CSV d'erreurs autorisé par imports.export_errors : numéro de ligne, statut, champ, code et message uniquement, sans nom ou autre donnée personnelle.

Permissions existantes imports.view/create/commit/export_errors, Policy serveur et contexte établissement actif. Comptes student et Scolarité Centrale refusés dans cette V1 ; superadmin/developer gardent leurs permissions explicites, sans bypass.

Traitement synchrone V1, **10 Mo, 5 000 lignes physiques de données, 50 colonnes**, expansion XLSX limitée à 100 Mo. Taille/lignes configurables dans `config/student_imports.php`. Lecture et inserts staging par blocs de 200 ; analyse et confirmation par blocs ; aucun chargement intégral des lignes en mémoire. Les clés de doublons/verrouillage sont conservées en mémoire (volume borné), pas le fichier complet. La confirmation reste une transaction globale : les chunks ne sont pas des commits partiels. Test automatisé de 1 001 lignes ; vérifier les temps et limites PHP/proxy sur le matériel cible avant de relever les bornes. Pour des lots plus grands, passer les Actions en Jobs avec suivi d'état ; aucun worker n'est requis en local dans cette phase.

## Export des étudiants au format du modèle

`/students/export` (bouton « Exporter (modèle Excel) » de la page Étudiants, permission `students.export`, limitation de débit) : les déclarations de la sélection affichée (année obligatoire, établissement, niveau, mention, parcours, recherche ; statut actif par défaut) dans le même classeur que le modèle, une ligne par déclaration, BOURSIER/TAUX remplis par la règle du serveur, bloc « Arrêté au nombre de » en bas. Le fichier se réimporte tel quel (nouvelle année ou autre plateforme). Contient des données personnelles (CIN, contacts) : audit `students_exported` avec filtres et nombre de lignes, sans valeurs.

## Demande de bourse déclarée par l'établissement

Le fichier modèle porte, pour chaque étudiant, la date de dépôt et la référence de sa demande de bourse auprès de l'établissement, ainsi que le code de redoublement (N, R, T+). Ces valeurs sont des **déclarations annuelles** (`student_records`), saisies par import ou par le formulaire de déclaration ; la référence est unique par établissement et année. Les colonnes BOURSIER et TAUX ne sont pas reprises : la fiche interne affiche le résultat de la règle du modèle calculé par le serveur (éligible : Malagasy, BAC au plus tard l'année limite définie sur l'année universitaire, référence présente ; taux 1 pour N, 1/4 pour R ; T+ non boursier ; « indéterminé » sans année limite). Ce résultat est indicatif : la réception et la décision du dossier papier par la Scolarité Centrale restent inchangées.

## Import/export des mentions et parcours

Sur `/references/mentions` et `/references/programs`, un compte autorisé (`programs.manage`, établissement limité au sien) dispose de :

- **Modèle** (`GET references/{kind}/template`) : XLSX avec une feuille « Mentions » ou « Parcours » (en-têtes seuls : `Code établissement`, `Code mention`, `Nom de la mention` ; pour les parcours `Code établissement`, `Code mention` facultatif, `Code parcours` facultatif, `Nom du parcours`) et une feuille « Codes » listant les établissements (et, pour les parcours, les mentions codées) du périmètre.
- **Export** (`GET references/{kind}/export`) : la liste filtrée (recherche, établissement) dans le périmètre, mêmes colonnes que le modèle, donc réimportable telle quelle. Toutes les cellules sont écrites en texte.
- **Import** : upload XLSX (première feuille) ou CSV `;`/`,` UTF-8 avec ou sans BOM, 2 Mo et 1 000 lignes au plus → analyse (codes mis en majuscules, mêmes règles que les formulaires) → prévisualisation `/references/{kind}/import/{token}` → confirmation explicite → transaction unique → retour à la liste avec « x créé(s), y mis à jour, z ignoré(s) ».
  Statuts : `à créer`, `à mettre à jour` (même établissement et même code, nom ou mention différent), `inchangé`, `erreur` (établissement inconnu ou hors périmètre, nom manquant/trop long, code invalide, mention inconnue pour l'établissement, code en double dans le fichier, doublon probable). Une ligne sans code ne met jamais à jour : elle est créée seulement si aucun référentiel du même nom n'existe dans l'établissement (identique et sans code → `inchangé`). Une ligne codée dont le nom existe déjà sans code est refusée (ajouter le code par le formulaire). Une mention vide sur un parcours existant dont la mention n'a pas de code conserve cette mention.
  Les lignes en erreur ou inchangées ne sont jamais importées ; la confirmation n'est possible que s'il reste au moins une création ou mise à jour. Pas de table : les lignes normalisées et l'analyse sont gardées 30 min en cache sous un jeton aléatoire lié au compte et au type. À la confirmation, l'analyse est refaite sous verrou des établissements concernés ; si le résultat a changé, rien n'est écrit et la prévisualisation est mise à jour.

## Navigation des étudiants (portail interne)

`/students` propose deux vues, avec un sélecteur en haut de page :

- **Par établissement** (vue par défaut) : cartes compactes établissement → niveau → mention → parcours → liste, avec le nombre de situations actives et inactives de l'année choisie (courante par défaut) et un fil d'Ariane cliquable. Un compte établissement commence directement à ses niveaux. À chaque étape, « Voir la liste de cette sélection » ouvre la liste filtrée sur l'étape en cours.
- **Tous les étudiants** (`view=list`) : la liste unique de tous les étudiants du périmètre, chacun avec son établissement, filtrable par année (ou « Toutes les années »), établissement, niveau, mention, parcours, statut et recherche (nom, prénoms, CIN, téléphone, email). 25 lignes par page, tri par nom ; les filtres restent dans l'URL et la pagination. Une recherche, un statut ou « Toutes les années » ouvrent toujours cette vue.

Compteurs en haut des deux vues (sélection en cours, sans tenir compte du filtre de statut) : étudiants (et nombre d'établissements), actifs, inactifs — cliquables, ils ouvrent la liste filtrée —, demandes de bourse, situations à compléter (code de redoublement manquant).

Sélection multiple dans la vue liste (cases à cocher par ligne et pour la page) : « Exporter la sélection » (Excel au format du modèle, situations cochées y compris inactives, `POST /students/export`), « Désactiver », « Réactiver » (situation de l'année) et « Supprimer » (corbeille, avec `trash.manage`) via `POST /students/bulk` (200 lignes au plus). Chaque ligne passe par la même Action que le bouton individuel (autorisation, contrôles, audit) : une ligne refusée n'empêche pas les autres, le message indique les lignes traitées, inchangées et refusées.

L'export Excel reprend la sélection affichée (une année est requise ; situations actives sauf statut choisi).

## Portail étudiant : compte et rattachement

Implémenté sur `bourse` (`/api/student/*`) :

1. L'étudiant saisit son email → code à 6 chiffres envoyé → vérification → compte `student` créé ou retrouvé, email vérifié. Variante : Google OAuth (email vérifié). Le compte seul ne donne accès à aucune fiche et ne vaut pas inscription.
2. Assistant « Retrouver ma fiche étudiante » en 3 étapes : date de naissance → choix d'**un seul** renseignement → saisie. Renseignements possibles, tels que déclarés par l'établissement : numéro CIN, numéro de téléphone, adresse mail, ou à défaut nom de famille **accompagné du nom de la mère** (le nom seul est trop facile à connaître).
3. **Rattachement immédiat, sans validation par le personnel** (l'email a déjà été prouvé par l'OTP ou Google) si une seule fiche active, non encore rattachée, correspond exactement (téléphone et CIN normalisés ; nom et nom de la mère sans casse ni accents). Sinon, réponse générique (« Ces informations ne correspondent à aucune fiche disponible… »), sans dire si la fiche existe ou est déjà prise ; limite de débit sur les essais.
4. Protections à la place d'une revue manuelle : une fiche = un compte (contrainte SQL) ; si l'email officiel de la fiche diffère de celui du compte, un email d'alerte lui est envoyé (« si ce n'est pas vous, contactez votre établissement ») ; le personnel ayant `student_links.review` peut **détacher le compte portail** depuis la fiche étudiant (onglet Vérification), action auditée `portal_detached`.
5. L'ancienne revue manuelle (`/student-links`, approbation/rejet) est supprimée ; les demandes encore en attente ont été closes (migration `2026_10_17_160000`), la table reste pour l'historique.
6. Une fois rattaché, l'étudiant consulte sa fiche et ses déclarations annuelles en lecture seule.

## Vérification étudiant

Identification → consultation → confirmer OU demander correction → établissement accepte/refuse/corrige. Les deux branches sont implémentées.

## Confirmation des informations (implémentée)

La confirmation signifie uniquement : « j'ai vérifié les informations actuellement affichées et je les considère correctes ». Ce n'est ni une inscription, ni une validation par l'établissement, ni une validation de bourse, ni une preuve d'identité, et elle ne modifie ni `Student` ni `StudentRecord`.

1. Page « Mes informations » sur `bourse`, section « Vérification de mes informations » : état `never_confirmed` (jamais confirmé), `confirmed` (« Informations confirmées le … », badge « Confirmées ») ou `stale` (« Vos informations ont changé depuis votre dernière confirmation », badge « À confirmer de nouveau »).
2. Action volontaire : bouton « Je confirme » (bloc de vérification, barre latérale droite), puis dialogue « Je confirme avoir vérifié les informations affichées » et bouton « Confirmer mes informations ». Rien n'est confirmé à l'affichage ; pas de case précochée.
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

Champs corrigibles : nom, prénoms, sexe, date de naissance, nom et prénoms de la mère, CIN (numéro, date et lieu de délivrance), nationalité, téléphone, email de contact, adresse, série/année du BAC. Exclue : toute la déclaration annuelle (année, établissement, niveau, parcours, code de redoublement, demande de bourse, statut) : l'étudiant est invité à contacter son établissement.

## Bourse (implémentée)

**La demande de bourse reste papier.** Aucun dépôt numérique, aucun upload, aucun formulaire de demande dans le portail étudiant en V1. La plateforme enregistre la réception du dossier papier et son traitement par la Scolarité Centrale.

1. **Campagne** (`/scholarships/campaigns`, `campaigns.manage`) : une campagne appartient à une année universitaire. Statuts `draft → scheduled | open`, `scheduled → open`, `open → closed`, `closed → archived`, sans retour. Ouverture refusée si l'année est inactive. Aucun changement automatique selon les dates en V1 : les dates sont indicatives, l'ouverture et la clôture sont manuelles. Pas de suppression.
2. **Réception** (`/scholarships/reception`, `scholarships.receive`) : uniquement pour une campagne `open`. Le secrétariat choisit la campagne, recherche l'étudiant côté serveur (nom, prénom, CIN, téléphone, email ; 2 caractères minimum, 20 résultats maximum), vérifie la fiche synthétique (nom, naissance, établissement, niveau, parcours, année) puis saisit le numéro DDB et des observations internes.
3. Le serveur impose l'étudiant (actif), la **déclaration annuelle** (l'unique déclaration `active` de l'année de la campagne, avec établissement, niveau et parcours actifs et cohérents), l'acteur et la date. Aucune déclaration, déclaration inactive ou situation ambiguë : refus avec message, aucun dossier créé. **Un dossier par étudiant et par campagne.**
4. **Vérification** (`scholarships.review`) : checklist de contrôles du dossier papier contre la fiche officielle (`conforme`, `anomalie`, `non applicable`, note facultative). Commencer la vérification ou enregistrer un premier contrôle fait passer le dossier `pending → in_review`.
5. **Décision** : `validated` (`scholarships.validate`) exige que tous les contrôles soient conformes ou non applicables, et que la fiche et la déclaration soient toujours actives ; `needs_correction` (`scholarships.request_correction`) et `rejected` (`scholarships.reject`) exigent un motif (10 à 1000 caractères).

Statuts du dossier : `pending` (reçu), `in_review`, `validated`, `needs_correction`, `rejected`. Transitions : `pending → in_review | validated | needs_correction | rejected` ; `in_review → validated | needs_correction | rejected` ; `needs_correction → in_review | validated | rejected`. `validated` et `rejected` sont définitifs en V1 (une réouverture serait une décision métier distincte). Un dossier d'une campagne archivée n'est plus modifiable ; une campagne clôturée bloque la réception, pas le traitement.

`needs_correction` concerne le **dossier papier** (pièce manquante, incohérence à clarifier). Il ne modifie jamais `Student` et ne crée pas de `StudentCorrectionRequest` : si une donnée officielle est fausse, le workflow de correction existant s'applique séparément.

Liste `/scholarships/applications` : pagination serveur (25), recherche (N° dossier, N° DDB, CIN, nom, prénom), filtres campagne, statut, établissement, niveau, période de réception. Export CSV (`scholarships.export`) des mêmes filtres. Historique métier visible sur chaque dossier.

## Historique

Ne jamais écraser un ancien `student_record` lors d'un changement d'année ; créer la déclaration de la nouvelle année.

## Suppression réversible et permissions

Personnel global autorisé → « Supprimer » → confirmation → contrôles des liens → corbeille. `/trash` liste par catégorie avec recherche/pagination ; restauration contrôlée avec audit ; purge définitive séparée et confirmée. Le déplacement conserve les données et les identifiants. Les référentiels utilisés restent désactivables, les campagnes restent archivables, les imports terminés peuvent être masqués en corbeille. Les dossiers déjà traités restent protégés de la purge. Les boutons sont proposés sur les listes des référentiels/imports/campagnes/dossiers et sur les listes/fiches étudiantes et leurs déclarations ; aucune suppression des rôles fixes ou de l'audit n'est exposée.

`/permissions` : rechercher les droits, modifier la matrice par rôle, vérifier les droits accordés/retirés dans la confirmation, enregistrer. Les changements sont effectifs après invalidation du cache. Réexécuter le seeder conserve les réglages ; seules les permissions/rôles nouveaux reçoivent leurs defaults de déploiement.

## Notifications des actions étudiantes

Quand un étudiant signale une erreur, les comptes autorisés de son établissement actuel, la Scolarité Centrale et le superadmin reçoivent une notification avec son nom et le champ concerné. L'ouverture mène à la demande : la Scolarité Centrale peut la consulter avec `corrections.view`, la validation exige toujours les droits de revue et de modification existants. Quand l'étudiant confirme ses informations, une notification mène à sa fiche ; cette confirmation n'est ni une décision de bourse ni une inscription universitaire. Developer et student ne sont pas destinataires de ces événements opérationnels.

La cloche affiche les notifications non lues et, séparément, les tâches encore à traiter. Elle se rafraîchit toutes les 30 secondes et à chaque navigation. `/notifications` propose pagination, filtres type/lu/non lu, ouverture, changement de lecture, « Tout marquer comme lu » et préférences personnelles par type. Marquer une notification lue ne traite pas la demande ; désactiver un type ne supprime pas l'historique ni les tâches à traiter. Les notifications devenues hors périmètre ou sans droit de consultation ne sont plus accessibles. `notifications:prune-read`, planifié quotidiennement, supprime uniquement les notifications étudiantes lues depuis plus de 90 jours ; les non lues restent conservées.
