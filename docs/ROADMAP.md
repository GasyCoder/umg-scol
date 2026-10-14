# Roadmap

## Phase 0 — Fondation

- [x] Spatie Permission
- [x] Spatie Activitylog
- [x] Laravel Excel
- [x] Pest installé et tests de fondation identité/permissions/audit
- [x] mécanisme de seed superadmin sécurisé et idempotent

## Phase 1 — Référentiels institutionnels implémentés

- [x] établissements
- [x] années universitaires
- [x] niveaux
- [x] parcours
- [x] catalogue et matrice initiale rôles/permissions
- [x] rattachement utilisateur/établissement et validation du contexte
- [ ] CRUD utilisateurs complet

## Phase 2 — Étudiants et déclarations manuelles implémentés

- [x] Student
- [x] StudentRecord
- [x] CRUD établissement
- [x] recherche/filtres
- [x] isolation

## Phase 3 — Imports Excel/CSV implémentés

- [x] upload
- [x] validation
- [x] preview
- [x] doublons
- [x] commit transactionnel
- [x] rapport erreurs

## Phase 4 — Vérification étudiant

- [x] portail `bourse` : compte OTP/Google et rattachement sécurisé
- [x] consultation
- [x] confirmation explicite des informations (empreinte HMAC, obsolescence déduite)
- [x] demande de correction (étudiant) et revue (personnel)

## Phase 5 — Bourse

- [x] campagnes
- [x] réception dossier papier
- [x] numéro DDB (saisie libre, doublon par campagne)
- [x] vérification (checklist générique, à remplacer par la liste officielle)
- [x] validation/correction/rejet, historique métier, export CSV

## Phase 6 — Audit/rapports

- [x] dashboards (par rôle : établissement, Scolarité Centrale, superadmin, developer)
- [x] exports (rapports agrégés étudiants/imports/bourses, export d'audit)
- [x] audit développeur (interface d'audit filtrée, détail assaini, developer tracé comme tout acteur)

## Phase 7 — Durcissement

- [ ] tests permissions/import/workflow
- [ ] performance
- [ ] sécurité
- [ ] sauvegardes

## Administration complémentaire

- [x] Import/export XLSX/CSV des mentions et parcours avec prévisualisation
- [x] Matrice de permissions modifiable, rôles fixes, réglages conservés au déploiement
- [x] Corbeille, restauration et purge contrôlées avec audit
- [x] Reprise des suppressions de fichiers après une indisponibilité du stockage
