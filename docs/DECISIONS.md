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
