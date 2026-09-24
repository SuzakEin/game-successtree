---
name: successtree-integration
description: >-
  Intègre SuccessTree (arbre de progression gamifié, zéro dépendance, PHP natif + JS vanilla) dans un projet hôte
  PHP, puis conçoit l'arbre à partir d'un brief visuel. À utiliser dès que l'utilisateur veut « intégrer l'arbre
  de succès », « brancher SuccessTree », « successtree », « arbre gamifié », « arbre de progression », « arbre de
  compétences », « arbre de talents », « constellation », « carte de constellation », « sphérier », « cœur de 9
  points », « hub central qui pousse les autres », « gamifier mon site », « succès / achievements », « débloquer
  des étapes », ou mentionne forceRequest avec un arbre. English triggers: "success tree", "skill tree",
  "talent tree", "tech tree", "progression tree", "achievement tree", "constellation map", "sphere grid",
  "policy tree", "gamified tree builder", "integrate SuccessTree", "gamification". Couvre : questionnaire de
  découverte visuel et fonctionnel, inspection du projet (singleton BDD, routeur, layout, auth), contrôleur hôte,
  adaptateur ForceRequestAdapter, installation des tables, génération du payload JSON (cœur, radial, anneau,
  libre, colonnes), vérification.
---

# Intégration SuccessTree

Tu guides l'intégration de SuccessTree dans un projet hôte, **sans modifier le code existant de l'hôte** hors du
nouveau contrôleur, de la route et de la vue. Références obligatoires (lis-les avant d'écrire du code) :

- `docs/SPEC.md` — contrat technique (fait foi).
- `docs/INTEGRATION.md` — protocole pas-à-pas numéroté (étapes 1 à 12 + références API/JSON/conditions).
- `examples/native-php/` — intégration type (singleton `forceRequest`, contrôleur, vue, seed).
- `brief-template.md` (ce dossier) — gabarit de brief visuel à remplir.
- `examples.md` (ce dossier) — 3 briefs → payloads JSON valides (cœur 9+1, Sphérier FFX, politiques Civ V).

Déroule les 7 phases dans l'ordre. Ne saute pas la phase 1 : l'utilisateur a presque toujours une image précise
en tête.

---

## Phase 1 — Découverte : questionnaire

Pose les questions **en un seul message**, regroupées, sous forme de tableau. Propose les valeurs par défaut pour
que l'utilisateur puisse répondre « défaut » ou ne corriger que quelques lignes. Si l'utilisateur fournit des
captures ou références, décris ce que tu y vois et déduis-en les réponses avant de demander confirmation.

| # | Question | Pourquoi | Valeur par défaut |
|---|---|---|---|
| 1 | **Forme globale** : cœur, radial (étoile), anneau, libre (réseau/grille), colonnes, ou coordonnées exactes fournies ? | détermine `tree.layout` et le calcul des positions | `heart` (cœur) |
| 2 | **Nombre de hubs** principaux ? | nombre de domaines / grandes branches | 9 |
| 3 | **Hub central** posé sur le départ ? Doit-il, une fois déployé, **pousser** les autres hubs (force de 1 à 2,5) ? | `meta.central`, `settings.expandPush` | oui, poussée 1,6 |
| 4 | **Noms et ordre** des hubs (et du central) ? | libellés et placement (le 1er hub du cœur est la pointe basse) | à proposer selon le thème |
| 5 | **Palette et ambiance** : fond, accent, couleur par hub ou uniforme ? Références (constellation map, Sphérier FFX, politiques Civ V, materia FF7, capacités FF9…) | `tree.theme`, `node.theme.color/glow` | fond `#2e2f6b`, accent lavande `#c8b6ff`, couleur par hub |
| 6 | **Icônes** : jeu intégré (target, heart, star, list, users, filter, database, globe, chart, building, check, layers, bolt, book, flag, crown, gem, shield, leaf, flame, lock), emoji, ou texte court ? | `node.icon` | icônes intégrées pour les hubs, aucune pour les points |
| 7 | **Labels** : toujours visibles, au survol, jamais ? | `settings.showLabels` | `hover` |
| 8 | **Densité et profondeur** : combien de branches par hub, combien de points par branche ? Ponts entre branches ? | génération des `nodes` et `links` | 2–3 branches, profondeur 2–4, quelques ponts |
| 9 | **Taille** des hubs et points ? | `settings.hubRadius`, `settings.nodeRadius` | 34 / 9 |
| 10 | **Conditions** : déblocage par parent seul, ou aussi `all`/`any`/`children`, métriques chiffrées, validation manuelle, règle métier codée (`callback`) ? | `conditions[]` (SPEC §5) | parent implicite + quelques `metric` et `manual` |
| 11 | **Sources des métriques** : quels événements métier (inscription, vente, article publié…) et où dans le code ? `inc` ou `set` ? | appels `metric` depuis le code hôte | à identifier en phase 2 |
| 12 | **Récompenses** : XP, badges ? Que faire à la complétion (hook `on_complete` : XP, notification, log) ? | `reward`, `on_complete` | `{xp}` + log |
| 13 | **Qui édite** l'arbre (mode Construire) ? **Qui valide** les étapes manuelles ? Le joueur peut-il se valider lui-même ? | `can_edit`, `can_progress` | admin édite et valide ; joueur ne se valide pas |
| 14 | **Sujet** : la progression est-elle par utilisateur, par équipe, par compte ? | `subject` | id utilisateur |
| 15 | **Route et contrôleur** cibles (URL de la page, URL de l'API) ? | contrôleur hôte, `endpoint` | `/arbre` et `/arbre/api` |
| 16 | **Accès BDD** : singleton avec `forceRequest` (nom exact de la méthode ?), PDO, autre ? Expose-t-il sa connexion (`getConnection()`…) ? | `ForceRequestAdapter` / `PdoAdapter`, option `escape` | `Database::getInstance()->forceRequest()` |
| 17 | **Préfixe** des tables ? | `prefix` | `st_` |
| 18 | **Langue** des libellés et de l'interface ? | contenu + `i18n` | français |

Consigne les réponses dans une copie de `brief-template.md` (bloc YAML) et montre-la à l'utilisateur pour
validation avant la phase 3.

---

## Phase 2 — Inspection du projet hôte

Localise, **sans rien modifier** :

```bash
# Singleton / accès BDD
grep -rn "function forceRequest\|getInstance()\|new mysqli\|new PDO\|mysqli_connect" --include=*.php . | grep -v vendor
grep -rn "function getConnection\|real_escape_string\|set_charset\|charset=" --include=*.php . | grep -v vendor

# Routeur / front-controller
grep -rln "REQUEST_URI\|PATH_INFO\|\$_GET\['route'\]\|\$_GET\['page'\]\|->get(\|->post(\|Route::" --include=*.php . | grep -v vendor
ls -a | grep -i htaccess; grep -rn "RewriteRule" .htaccess 2>/dev/null

# Layout / vues
grep -rln "<html\|<!doctype" --include=*.php . | grep -v vendor | head
grep -rn "Content-Security-Policy" -r . | grep -v vendor

# Auth / session / rôle admin
grep -rn "session_start\|\$_SESSION\[" --include=*.php . | grep -v vendor | head -20
grep -rni "isAdmin\|is_admin\|role\b\|hasRole\|can(" --include=*.php . | grep -v vendor | head -20

# Version PHP, extensions
php -v; php -m | grep -Ei "mysqli|pdo_mysql|json"
```

Note pour chaque point : fichier, ligne, nom exact (classe, méthode), format de retour de `forceRequest` (lire son
code : tableau de lignes ? `mysqli_result` ? `false` en erreur ?), charset de la connexion, façon dont une route
appelle un contrôleur, comment le layout inclut une vue, où se trouve l'id utilisateur et le rôle admin.

---

## Phase 3 — Plan d'intégration

Présente un plan court, validé par l'utilisateur avant d'écrire :

1. Emplacement du paquet (copie ou `git submodule`) et chemin de `src/autoload.php`.
2. Adaptateur : `new SuccessTree\Db\ForceRequestAdapter(<singleton>, ['method'=>…, 'escape'=>…, 'driver'=>'mysql'])`.
3. Fichiers créés : contrôleur hôte, vue, déclaration de route, script de seed. **Aucun fichier hôte existant modifié**
   sauf l'ajout de la route (une ou deux lignes) — signale-le explicitement.
4. Installation des tables (action `install` ou `schema/mysql.sql`).
5. Payload de l'arbre généré depuis le brief (phase 5).
6. Points d'appel métier pour les métriques (fichier:ligne) et callbacks.
7. Tests de recette (phase 6).

---

## Phase 4 — Implémentation

Suis `docs/INTEGRATION.md` étape par étape (3 → 9) en t'appuyant sur `examples/native-php/` :

- `require_once …/src/autoload.php` ;
- contrôleur hôte : `api()` → `$st->handle(SuccessTree\Http\Request::fromGlobals())->send()` ;
  `page()` → vue hôte qui affiche `$st->renderMount($slug, ['height'=>'80vh','edit'=>$isAdmin])` ;
- config façade : `subject` = id utilisateur **depuis la session** (jamais depuis la requête), `can_edit` = admin,
  `can_progress` = `false` pour le navigateur, `endpoint` = URL exacte de l'API, `conditions`, `on_complete` ;
- hôte qui gère lui-même la sortie : `$st->handleArray($action, $params, $body)` renvoie un tableau à sérialiser ;
- progression métier : instance « de confiance » (`can_progress = true`) pour le sujet concerné, puis
  `handleArray('metric', ['tree'=>$slug], ['metric'=>…, 'value'=>…, 'mode'=>'inc'|'set'])`, `complete`, `evaluate` ;
- seed : `handleArray('install', [], [])` puis `handleArray('save', ['tree'=>$slug], $payload)`
  (`SuccessTree\Seed\HeartDemo::payload()` pour la démo cœur).

---

## Phase 5 — Génération du payload JSON depuis le brief

Produis un payload conforme à SPEC §4 (`tree`, `nodes`, `links`, optionnellement `metrics`, `permissions`) :

**Structure**
- Exactement **un** nœud `kind: "origin"` (le départ), en `x: 0, y: 0`.
- Hubs : `kind: "hub"`, `parent` = uid de l'origin (ou `null` : un hub sans parent dépend de l'origin).
- Points de branche : `kind: "node"`, `parent` = nœud précédent de la branche.
- `uid` : chaînes lisibles (≤ 64 car.) et **uniques dans toute la base**, pas seulement dans le payload : ce sont
  des clés primaires partagées par tous les arbres. Préfixe-les par arbre, ex. `ent_h_ventes`, `ent_n_ventes_2_3`,
  `ent_c012`, `ent_l_bridge_1`. Jamais d'entier auto-incrémenté. Un `save` d'un arbre existant doit garder son
  `tree.uid` (sinon : « tree.uid does not match the existing tree »).
- `slug` optionnel mais conseillé (`[a-z0-9-]`, sans accents).
- Ne mets pas de `status` : il est calculé.

**Positions** (unités monde, origine (0,0), **y vers le bas**, R ≈ 420)
- `heart` : hub k (k = 0..8) à t = π + 2πk/9, `x = 16 sin³t`, `y = −(13 cos t − 5 cos 2t − 2 cos 3t − cos 4t)`,
  divisés par 16 (comme `Layout::heart()`) puis × R, arrondis à 0,1 (k = 0 : pointe basse ; puis paires gauche/droite). Calcule-les avec un
  script (`node -e` / `php -r`) plutôt qu'à la main, ou laisse `x`/`y` à `null` et `tree.layout: "heart"` pour que
  le preset JS/PHP les place.
- `radial` : hubs à `(R cos θ, R sin θ)`, θ = −π/2 + 2πk/n (le premier en haut).
- `ring` : idem sur un seul anneau, branches courtes vers l'extérieur.
- Colonnes (type Civ V) : x fixe par colonne (espacement ≈ 360), rangs descendant de ≈ 110.
- Hub central : `(0, −72)` environ (au-dessus de l'origin, cliquable), `meta: {"central": true}` ; ses enfants en
  cercle de rayon ≈ 72 autour de lui, en évitant l'angle qui pointe vers l'origin.
- Attention au preset cœur : les hubs k = 4 et 5 (bord du creux) ne sont qu'à ≈ 34 unités l'un de l'autre à
  R = 420 ; écarte leurs branches (±0,45 rad) et préviens l'utilisateur si `hubRadius` est grand.
- Branches : direction = vecteur origin → hub ; 1er point à ≈ 68 du hub, puis ≈ 46 ; éventail ±0,3–0,5 rad entre
  branches ; écarte les hubs trop proches (ex. les deux hubs du creux du cœur) pour éviter les chevauchements.
- Positions inconnues : `null` → auto-layout JS.

**Apparence**
- `tree.theme` : `background`, `accent`, `line`, `font` ; `tree.settings` : `expandPush`, `hubRadius`,
  `nodeRadius`, `showLabels`.
- `node.theme` : `color`, `glow`, `shape`, `size`, `label` ; `node.icon` : icône intégrée, emoji ou texte court.
- `reward` : JSON libre (`{"xp": 10, "badge": "…"}`) ; `meta` : JSON libre de l'hôte.

**Conditions** (SPEC §5) — chaque condition : `{uid, phase: "unlock"|"complete", type, params}`
- aucune condition `unlock` ⇒ `parent` implicite ; `node {node}` ; `all {nodes}` ; `any {nodes, min}` ;
  `children {min|null}` ; `metric {metric, op, value}` (op ∈ `> >= < <= == !=`, value numérique) ; `manual {}` ;
  `callback {name}` (le nom doit exister dans `config['conditions']` côté hôte).
- Auto-complétion : seulement si toutes les conditions `complete` sont vraies, qu'il y en a au moins une et
  qu'aucune n'est `manual`.
- **Une condition `unlock` explicite remplace le parent implicite** (parent + sources des liens `path`) : si le nœud
  doit aussi attendre son parent, mets `{"type":"all","nodes":[parent, autre]}`.
- **Interblocages à éviter** : `children` en phase `complete` sur un hub/nœud dont les enfants n'ont que le parent
  implicite (ils attendent le parent, qui les attend) ; `children` sur un nœud sans enfant n'est jamais vrai.
  Pour un hub central qui « se complète quand ses facettes le sont » : enfants en `unlock node → origin`, hub en
  `complete all [enfants]`.
- Teste la jouabilité : sur un sqlite en mémoire (`PdoAdapter`), `save`, métriques au maximum, puis `complete` en
  boucle sur les nœuds `available` ; tous doivent finir `completed`.

**Liens** : `{uid, from, to, type: "path"|"visual"}` ; `path` = visuel + prérequis (s'ajoute au parent implicite,
ignoré si le nœud a des conditions `unlock` explicites), `visual` = décoratif (ex. contour
du cœur entre hubs voisins). Nombre illimité ; `from`/`to` doivent exister.

**Validation obligatoire avant `save`** : uid uniques, un seul origin, parents existants, pas de cycle de parents,
références de conditions existantes, liens valides (script de `examples.md`). Sauvegarde le payload dans le dépôt
hôte (ex. `data/successtree/<slug>.json`) pour pouvoir le rejouer.

---

## Phase 6 — Vérification

1. Checklist de recette de `docs/INTEGRATION.md` §11, cochée ligne par ligne.
2. Tests HTTP :
   ```bash
   curl -s 'http://localhost:8000/arbre/api?action=list'
   curl -s 'http://localhost:8000/arbre/api?action=tree&tree=coeur' | head -c 600
   curl -s -o /dev/null -w '%{http_code}\n' 'http://localhost:8000/arbre/api?action=asset&file=successtree.js'   # 200
   curl -s -o /dev/null -w '%{http_code}\n' -X POST 'http://localhost:8000/arbre/api?action=save'              # 403 si non admin
   ```
3. Contrôle visuel (navigateur ou capture) : forme conforme au brief, couleurs, labels, hub central qui se déploie
   et **pousse** les autres, statuts (`locked` pointillé, `available` pulsant, `completed` lumineux), panneau de
   détail, pan/zoom, mode Construire pour l'admin.
4. Progression : déclenche une métrique depuis le code métier et vérifie la complétion + le hook `on_complete`.
5. Accents et emojis corrects après un aller-retour `save` → `tree`.

Termine par un récapitulatif : fichiers créés, ligne(s) de route ajoutée(s), payload, points d'appel métier,
éléments restant à décider.

---

## Phase 7 — Règles impératives

- **Ne jamais modifier le DAO / singleton BDD de l'hôte.** Si son format de retour pose problème, écris un
  adaptateur (implémentation de `DbAdapterInterface`) à côté.
- **Ne jamais concaténer une valeur non échappée dans du SQL** : passe toujours par l'adaptateur (`quote()`) ;
  fournis l'option `escape` reliée au `real_escape_string` de la connexion quand elle est accessible ; exige utf8mb4.
- **Aucune dépendance ajoutée** (ni Composer, ni npm, ni CDN) : PHP natif, JS vanilla, CSS pur.
- `subject`, `can_edit`, `can_progress` viennent de l'auth hôte, jamais de paramètres de requête.
- Ne pas modifier `src/`, `assets/`, `schema/` du paquet pour un besoin spécifique : utiliser `theme`, `settings`,
  `meta`, `conditions` (callbacks) et `on_complete`.
- Ne pas désactiver la CSP de l'hôte : adapter la source des assets.
- Toujours valider le payload avant `save` (qui remplace l'arbre entier du `tree_uid`).
