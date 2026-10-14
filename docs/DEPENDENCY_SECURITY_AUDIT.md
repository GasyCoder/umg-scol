# Audit de sécurité des dépendances — 14 octobre 2026

## Périmètre et état initial

Audit de `composer.lock` et `package-lock.json` à partir du commit `38058e9`, sans changement du code métier, des rôles, des routes ou de l'interface. Environnement : PHP 8.4.26, Node 22.23.3, npm 10.9.9.

- `composer audit --locked` : aucune vulnérabilité et aucun package abandonné signalés, dépendances de production et de développement incluses. Aucun package Composer n'a été modifié.
- `npm audit` : quatre paquets signalés (trois critiques et un faible), correspondant à trois advisories distincts. Les lignes critiques `oxfmt` et `vite-plus` sont des propagations de l'alerte `tinypool`, pas des failles supplémentaires indépendantes.
- `npm audit --omit=dev` : une alerte faible Babel. Aucun signalement critique ou haut dans ce graphe.
- API GitHub Dependabot : trois alertes ouvertes, deux critiques et une faible ; toutes transitives, scope `development`, manifest `package-lock.json`. Aucune alerte Composer.

## Vulnérabilités identifiées

| Package installé         | Advisory / CVE                                                                            | Sévérité npm et Dependabot | Chaîne                                                                                                         | Versions corrigées                                         |
| ------------------------ | ----------------------------------------------------------------------------------------- | -------------------------- | -------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------- |
| `tinypool` 2.1.0         | [GHSA-5gmw-xhrv-c9v3](https://github.com/advisories/GHSA-5gmw-xhrv-c9v3), CVE-2026-104848 | Critique                   | `vite-plus` 0.3.0 → `oxfmt` 0.64.0 → `tinypool`                                                                | 2.1.1 corrige cet advisory ; 2.1.2 couvre aussi le suivant |
| `tinypool` 2.1.0         | [GHSA-85c8-ppgw-ccpr](https://github.com/advisories/GHSA-85c8-ppgw-ccpr), CVE-2026-104849 | Critique                   | Même chaîne                                                                                                    | 2.1.2                                                      |
| `@babel/core` 8.0.0-rc.4 | [GHSA-4x5r-pxfx-6jf8](https://github.com/advisories/GHSA-4x5r-pxfx-6jf8), CVE-2026-49356  | Faible                     | `@rolldown/plugin-babel` 0.2.4 → peer `@babel/core`, aussi consommé par le preset `@vitejs/plugin-react` 6.1.2 | 8.0.0-rc.6, ou 7.29.6 sur la branche 7                     |

Les deux failles Tinypool peuvent convertir une pollution de prototype préalable en exécution de code dans les workers via leurs options. L'exposition identifiée ici concerne le formatter, la machine développeur et les runners CI, avec les droits du processus de build. Aucun appel Tinypool depuis les requêtes Laravel ni le code applicatif React n'a été identifié ; cela ne retire pas le risque de chaîne d'approvisionnement.

La faille Babel peut permettre de lire une source map locale en compilant un code malveillant si l'attaquant contrôle le code source, connaît le chemin et peut consulter le résultat. Ici Babel sert à la compilation, via `vite.config.ts` ; aucun service de compilation de contenu utilisateur n'est implémenté. Le risque potentiel concerne des sources de build non fiables et la confidentialité du poste/CI.

La catégorie npm `dependencies` n'est pas une preuve d'exécution en production : `@vitejs/plugin-react` y est déclaré, ce qui maintient son peer Babel dans `audit --omit=dev`. Dependabot le classe en développement. Aucun outil n'a été déplacé dans une autre catégorie pour rendre l'audit artificiellement vert.

Les pages éditeur Tinypool affichent une sévérité « High », alors que npm et l'API Dependabot renvoient « critical » ; la priorité la plus élevée a été retenue. Pour Babel, la borne de la plage affectée npm (`< rc.5`) et la version de correctif annoncée par GitHub (`rc.6`) diffèrent : le correctif conservateur rc.6 a été choisi.

## Corrections ciblées

Deux overrides dans `package.json` :

- `oxfmt` → `tinypool: 2.1.2` : `oxfmt` 0.64.0 verrouille exactement Tinypool 2.1.0 ; le remplacement limité à cette branche applique les deux correctifs sans changer Vite Plus ni son formatter/linter.
- `@babel/core: 8.0.0-rc.6` : mise à jour du peer déjà présent, compatible avec la plage Babel 8 du plugin installé. Les sous-paquets Babel ont été résolus dans leurs plages compatibles requises par ce core ; ils sont détaillés ci-dessous.

Pas de `npm audit fix --force`, ni de major upgrade des frameworks, ni de changement Composer. Laravel, React, Inertia, TypeScript, Vite, Vite Plus, Oxfmt et les plugins parents restent à leurs versions initiales. Le lockfile conserve les métadonnées de plateformes non concernées.

Les overrides restent nécessaires tant que les parents n'intègrent pas ces correctifs par leur résolution normale. Leur retrait devra être précédé d'une comparaison du graphe, d'un nouvel audit et des mêmes validations. Aucune incompatibilité n'a été observée dans les validations actuelles.

## Versions du lockfile avant/après

| Package / chemin                                        | Avant      | Après        |
| ------------------------------------------------------- | ---------- | ------------ |
| `@babel/code-frame`                                     | 8.0.0-rc.4 | 8.0.6        |
| `@babel/compat-data`                                    | 8.0.0-rc.4 | 8.0.5        |
| `@babel/core`                                           | 8.0.0-rc.4 | 8.0.0-rc.6   |
| `@babel/generator`                                      | 8.0.0-rc.4 | 8.0.6        |
| `@babel/generator/node_modules/@jridgewell/gen-mapping` | absent     | 0.4.0-beta.0 |
| `@babel/helper-compilation-targets`                     | 8.0.0-rc.4 | 8.0.7        |
| `@babel/helper-globals`                                 | 8.0.0-rc.4 | 8.0.6        |
| `@babel/helper-string-parser`                           | 8.0.0-rc.4 | 8.0.6        |
| `@babel/helper-validator-identifier`                    | 8.0.0-rc.4 | 8.0.6        |
| `@babel/helper-validator-option`                        | 8.0.0-rc.4 | 8.0.0        |
| `@babel/helpers`                                        | 8.0.0-rc.4 | 8.0.7        |
| `@babel/parser`                                         | 8.0.0-rc.4 | 8.0.7        |
| `@babel/template`                                       | 8.0.0-rc.4 | 8.0.0        |
| `@babel/traverse`                                       | 8.0.0-rc.4 | 8.0.7        |
| `@babel/traverse/node_modules/obug`                     | absent     | 3.0.0        |
| `@babel/types`                                          | 8.0.0-rc.4 | 8.0.6        |
| `find-up-simple`                                        | absent     | 1.0.1        |
| `flru`                                                  | absent     | 1.0.2        |
| `lru-cache`                                             | 7.18.3     | retiré       |
| `tinypool`                                              | 2.1.0      | 2.1.2        |
| `verkit`                                                | absent     | 0.5.0        |

## Résultats finaux locaux

- `npm ci --ignore-scripts` : réinstallation reproductible réussie à partir des overrides et du lockfile. Cette vérification ne remplace pas les commandes de build qui exécutent effectivement l'outillage.
- Composer : audit verrouillé, exigences de plateforme et validation du manifeste réussis ; aucune vulnérabilité signalée.
- npm : audits complets et `--omit=dev` à zéro vulnérabilité.
- Backend : 106 tests, 104 réussis, deux tests historiques d'inscription Fortify ignorés, 591 assertions ; PHPStan et Pint réussis via `composer test`.
- Frontend : TypeScript et build réussis. Le premier contrôle de formatage a signalé `package.json` ; après formatage ciblé, `npm run check` réussit.
- La réinstallation signale aussi deux bindings Yuku 0.5.48 dépréciés (transitifs, versions inchangées). Il ne s’agit pas de signalements de vulnérabilité ; aucun upgrade parent non nécessaire n’a été effectué pour les éliminer.
- `git diff --check` réussi. Le build conserve l'avertissement non bloquant préexistant sur le package optionnel `fontaine` absent.

## Correspondance GitHub

Les alertes initiales [#3](https://github.com/GasyCoder/umg-scol/security/dependabot/3) et [#2](https://github.com/GasyCoder/umg-scol/security/dependabot/2) concernent Tinypool et les deux GHSA ci-dessus ; [#1](https://github.com/GasyCoder/umg-scol/security/dependabot/1) concerne Babel et son GHSA. Les versions initiales du lockfile appartiennent aux plages affectées, les nouvelles versions en sortent et atteignent les correctifs annoncés.

La fermeture distante doit être contrôlée après le push, sans déduire son état de la seule réussite des audits locaux. Aucun dismiss manuel n'a été effectué. L'état GitHub constaté après push sera indiqué dans le rapport de livraison.

Aucune vulnérabilité connue ne reste ouverte dans les audits locaux à cette date ; ce constat dépend des bases d'advisories consultées et ne constitue pas une garantie d'absence de toute vulnérabilité. Aucun autre module n'a été commencé.
