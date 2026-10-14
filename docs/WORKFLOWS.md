# Workflows métier

## Déclaration étudiant

Inscription réelle hors système → établissement confirme → ajout/import → `student` + `student_record` → visible Scolarité Centrale.

## Import

Upload → parsing → validation structure → validation lignes → doublons → preview → confirmation → transaction DB → résumé.

## Vérification étudiant

Identification → consultation → confirmer OU demander correction → établissement accepte/refuse/corrige.

## Bourse

Dépôt papier → recherche étudiant → comparaison dossier/base → `validated` / `needs_correction` / `rejected`.

## Historique

Ne jamais écraser un ancien `student_record` lors d'un changement d'année ; créer la déclaration de la nouvelle année.
