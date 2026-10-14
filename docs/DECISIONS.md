# Décisions d'architecture

- ADR-001 : un seul backend Laravel 13 / PHP 8.4+, Inertia pour le portail interne et API dédiée pour le portail étudiant (voir ADR-009).
- ADR-002 : React + TypeScript + shadcn/ui.
- ADR-003 : MySQL.
- ADR-004 : aucun module d'inscription universitaire.
- ADR-005 : séparation `Student` / `StudentRecord`.
- ADR-006 : dossier de bourse papier en V1 ; le système trace le traitement.
- ADR-007 : audit obligatoire, y compris développeur.
- ADR-008 : import massif avec prévisualisation obligatoire.
- ADR-009 : deux portails : `scol.umg.test:8000` pour Scolarité Centrale, établissements, superadmin et developer ; `bourse.umg.test:8000` pour les étudiants uniquement. API prévue sous `/api/student/*`, dans le même backend. Cette décision remplace l'hypothèse initiale d'une V1 sans API étudiante. Aucun endpoint étudiant ni routage par domaine n'est implémenté lors de la stabilisation du socle.
- ADR-010 : authentification étudiante prévue par email + OTP ou Google OAuth, puis rattachement sécurisé à une fiche `Student` existante. Un compte vérifié ne vaut ni inscription universitaire ni preuve de propriété d'une fiche. La fréquence OTP, le rattachement et le mécanisme de session API restent à définir.
- ADR-011 : l'étudiant propose une correction ; l'établissement décide de la modification des données officielles. Aucun accès étudiant en écriture directe à l'identité administrative.
- ADR-012 : MySQL est le moteur local et celui du setup CI ; SQLite en mémoire est conservé pour les tests existants. Le fichier SQLite local résiduel est conservé jusqu'à une décision explicite de nettoyage.

- ADR-013 : fondation d'accès avec Spatie Permission, cinq rôles et 33 permissions explicites. Superadmin et developer ont le catalogue complet sans bypass global ; la portée réelle sera contrôlée par les futures Policies métier.
- ADR-014 : provisionnement superadmin par variables d'environnement et seeder transactionnel idempotent, sans compte de démonstration, sans mot de passe codé en dur et sans rotation implicite du mot de passe existant.
- ADR-015 : désactivation globale de l'inscription Fortify actuelle tant que le routage par domaine est absent. La future inscription étudiante sera mise en place séparément, sans réouverture de la création publique de comptes administratifs.
- ADR-016 : audit synchrone de User par liste de champs autorisés et audit des attributions/retraits Spatie. Aucune exception developer, aucune journalisation de secrets, aucune purge automatique.

- ADR-017 : référentiels institutionnels administrables par les permissions existantes, avec routes web Inertia, pagination serveur et désactivation. Aucune liste officielle UMG ni donnée de démonstration n'est seedée.
- ADR-018 : au plus une année courante, garantie par index unique sur une colonne générée nullable. Changement transactionnel avec verrouillage ordonné, mises à jour Eloquent auditées et retrait du statut courant lors de la désactivation. Aucun effacement des années historiques.
- ADR-019 : code de parcours unique par établissement lorsqu'il est renseigné ; nom libre et non unique globalement. FK restrictives vers les établissements ; aucune relation niveau/parcours imposée.
- ADR-020 : rattachement utilisateur/établissement nullable en base, avec Action validée et contrôle de contexte sur les routes métier internes. Les comptes établissement sans rattachement actif sont inexploitables sur ces routes ; aucun CRUD utilisateur n'est créé dans cette phase.
- ADR-021 : audit des champs structurels des référentiels ; les changements limités aux contacts sont tracés sans leurs valeurs. Programmes et établissements sont filtrés par périmètre lorsque le rôle établissement reçoit une permission de gestion, sans modification de la matrice initiale.

- ADR-022 : identité permanente Student distincte de StudentRecord annuel. Le module centralise les étudiants déjà régulièrement inscrits hors plateforme ; aucune inscription/admission/paiement universitaire.
- ADR-023 : identifiants personnels indexés sans UNIQUE global en base. La saisie manuelle normalisée bloque les doublons évidents sous verrou partagé ; aucun rapprochement automatique ou exposition d'identité étrangère. La qualité des données historiques et les similitudes seront traitées avec le futur import.
- ADR-024 : une fiche par étudiant/année/établissement et au plus une fiche annuelle active par étudiant/année via index généré nullable. Historique conservé, transfert uniquement après désactivation explicite par des acteurs autorisés ; aucune mutation silencieuse de l'année ou de l'établissement d'une fiche.
- ADR-025 : parcours cohérent avec l'établissement par validation serveur et FK composite. Statuts active/inactive concernent uniquement la déclaration dans la plateforme ; identité et déclaration ont des activations distinctes.
- ADR-026 : établissements limités à leurs déclarations et à leurs identités visibles ; une identité partagée historiquement ne peut être corrigée globalement que par un acteur autorisé global. Scolarité Centrale en lecture seule, comptes student explicitement refusés, aucun lien User étudiant vers Student dans cette phase.
- ADR-027 : audit Student par noms de champs modifiés, sans valeurs personnelles ; audit annuel par IDs/statut/acteur/dates administratives. Création identité + première déclaration et modifications annuelles transactionnelles, audit inclus.
- ADR-028 : liste interne par déclaration annuelle, pagination SQL de 20 lignes, recherche par préfixe des identifiants/contacts et sous-chaîne des noms. Eager loading ciblé ; aucune exportation ou API étudiante ajoutée.
