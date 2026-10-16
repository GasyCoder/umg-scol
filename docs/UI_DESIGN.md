# UI_DESIGN.md — Système visuel du portail

Référence de la refonte UI/UX (ADR-062). Toute nouvelle page suit ces règles ; on réutilise les composants listés avant d'en créer.

## Identité

- Institutionnel et sobre : bleu roi `--primary`, neutres froids, barre latérale marine, accent or (`--brand-gold`) réservé aux repères (logo, entrée active, compteurs).
- Typographie : Public Sans (texte), Plus Jakarta Sans (`font-heading`, titres et chiffres clés).
- Rayon `--radius` 0.625rem ; cartes `rounded-xl`, bandeaux `rounded-2xl`.
- Vocabulaire : dans l'interface, une déclaration annuelle (StudentRecord) s'appelle « situation de l'année » ; le terme « déclaration » reste technique.
- Mode clair, sombre et système : bascule dans la barre supérieure et sur les écrans de connexion. **Jamais de couleur codée en dur** (`text-green-600`, `bg-zinc-900`, `#xxxxxx`) : uniquement les tokens, pour que le mode sombre suive.

## Tokens (resources/css/app.css)

| Usage      | Classes                                                                                                                                                                     |
| ---------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Surfaces   | `bg-background` (page), `surface` (= carte : `rounded-xl border bg-card shadow-card`), `bg-muted`                                                                           |
| Texte      | `text-foreground`, `text-muted-foreground`                                                                                                                                  |
| Action     | `bg-primary text-primary-foreground`, `bg-accent text-accent-foreground` (teinte douce)                                                                                     |
| Statuts    | `success`, `warning`, `info`, `danger` en trois variantes : `bg-success` (plein), `bg-success-soft` (fond doux), `text-success-foreground` (texte lisible sur le fond doux) |
| Graphiques | `bg-chart-1` … `bg-chart-5`                                                                                                                                                 |
| Ombres     | `shadow-card` (repos), `shadow-elevated` (survol, bandeaux)                                                                                                                 |
| Tableaux   | `<table className="data-table">` dans `surface overflow-hidden` + `div.overflow-x-auto`                                                                                     |
| Motif      | `bg-grid` (pointillés, couleur = `currentColor`)                                                                                                                            |

## Composants

- `PageHeader` / `PageBody` (`components/page-header.tsx`) : en-tête de page avec tuile d'icône, titre `h1`, description, `meta` (badges) et `actions` à droite. `PageBody` = espacement standard `p-4 md:p-6 lg:p-8 space-y-6`.
- `EmptyState` (`components/empty-state.tsx`) : illustration + titre + texte + action. Illustrations : `inbox`, `search` (aucun résultat), `done` (tout est traité), `upload`, `scholarship`, `students`, `secure`.
- Illustrations SVG (`components/illustrations.tsx`) : dessinées avec les tokens, donc compatibles clair/sombre. Pas d'images externes.
- `ToneBadge` + `toneFor(status)` (`components/tone-badge.tsx`) : pastille de statut à fond doux + point coloré. Le texte reste toujours écrit (jamais la couleur seule).
- `StatusBadge` (dossiers, campagnes) et `CorrectionStatusBadge` s'appuient sur `ToneBadge`.
- `Panel`, `KpiCard` (icône + tonalité), `BarList` (`colorful` pour les séries catégorielles), `YearLine`, `YearSelect` : `components/reporting/shared.tsx`.
- `Pagination` (`components/scholarships/shared.tsx`) : masquée s'il n'y a qu'une page.
- Formulaires : `FormSection`, `FormField`, barre d'actions collante (`components/forms/form-kit.tsx`).

## Coque

- Barre latérale marine (`AppSidebar`), groupes : Général, Étudiants, Bourses, Pilotage, Référentiels, Administration. Définition unique dans `hooks/use-navigation.ts` (aussi utilisée par la recherche de pages). L'entrée reste active sur ses sous-pages ; compteur or = travail en attente.
- Séparateur glissable entre la barre latérale et le contenu (`components/sidebar-resizer.tsx`) : 224 à 400 px, flèches/Origine/Fin au clavier, double-clic pour revenir à 256 px, relâché tout à gauche il replie le menu en icônes. Largeur gardée dans le cookie `sidebar_width`, relue par le serveur (`sidebarWidth`) pour un premier rendu sans saut.
- Barre supérieure : à gauche la recherche de pages (Ctrl K, navigation seulement, aucune donnée), sans titre ni fil d'Ariane (le `PageHeader` nomme la page) ; à droite thème et cloche dans des boutons ronds distincts, puis le compte (nom + rôle).
- Cloche : notifications étudiantes persistantes (`NotificationInbox`) et tâches à traiter calculées dans le périmètre des Policies (`PendingTasks`). Lire une notification ne clôture pas la tâche associée.

## Patterns de page

- Liste : `PageHeader` (+ action principale) → barre de filtres dans une `surface` → onglets/compteurs de statut si utile → tableau `data-table` → `Pagination`. Recherche/filtre sans résultat : `EmptyState illustration="search"` ; liste vide : `inbox` ou illustration métier.
- Fiche : `PageHeader` avec badges de statut en `meta` et actions → grille `lg:grid-cols-3` (contenu `lg:col-span-2` + panneau latéral) → sections en `surface`.
- Formulaire : `FormSection` par bloc, erreurs résumées en haut, actions en barre collante.
- Alertes en ligne : fond doux de la tonalité (`bg-warning-soft text-warning-foreground border-warning/30`) avec icône.
- Confirmations destructrices : `Dialog` avec bouton `destructive`.
- Accessibilité : icônes décoratives `aria-hidden`, cibles ≥ 36 px, `focus-visible:ring-2 ring-ring`, contraste AA dans les deux thèmes, `prefers-reduced-motion` respecté.

## Notifications

La cloche présente les derniers événements étudiants avec leur état de lecture ; son badge compte les notifications non lues. La section « À traiter » reste indépendante. La page `/notifications` utilise les mêmes surfaces institutionnelles, avec filtres, pagination, états vides et préférences personnelles. Le flux partagé est distinct de la pagination et se rafraîchit toutes les 30 secondes.
