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

## Vérification étudiant

Identification → consultation → confirmer OU demander correction → établissement accepte/refuse/corrige.

## Bourse

Dépôt papier → recherche étudiant → comparaison dossier/base → `validated` / `needs_correction` / `rejected`.

## Historique

Ne jamais écraser un ancien `student_record` lors d'un changement d'année ; créer la déclaration de la nouvelle année.
