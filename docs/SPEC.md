# SuccessTree — Spécification technique (contrat commun v1)

Constructeur d'arbres de progression gamifiés (façon Sphérier FFX, Civ V, FF7 materia/FF9 abilities,
"constellation map" des captures de référence). **Zéro dépendance** : PHP natif ≥ 7.4 (compatible 8.x),
JavaScript vanilla (ES2017, aucun build), CSS pur. Branchable sur n'importe quel projet.

## 1. Principes

1. **Le projet hôte garde la main** : il expose UNE route, son contrôleur instancie `SuccessTree\SuccessTree`
   et lui délègue la requête. SuccessTree ne fait ni routing global, ni session, ni auth.
2. **Accès BDD minimal** : l'hôte fournit un objet possédant `forceRequest(string $sql)` (singleton maison),
   enveloppé dans `SuccessTree\Db\ForceRequestAdapter`. Aucun autre couplage au DAO de l'hôte.
   Toute valeur est échappée par l'adaptateur (`quote()`), jamais concaténée brute.
3. **Identifiants générés côté application** (chaînes `uid`), jamais d'auto-incrément : pas besoin de
   `LAST_INSERT_ID()`, l'éditeur JS peut créer des nœuds hors-ligne.
4. **Données génériques** : tout ce qui est visuel ou métier et non prévu va dans des colonnes JSON (`theme`, `meta`, `params`).

## 2. Arborescence

```
src/
  autoload.php                     # spl_autoload_register PSR-4 "SuccessTree\\" => src/
  SuccessTree.php                  # façade : new SuccessTree($db, $config) ; ->handle($request) ; ->renderMount()
  Db/DbAdapterInterface.php
  Db/ForceRequestAdapter.php       # enveloppe l'objet hôte (forceRequest)
  Db/PdoAdapter.php                # pour tests / démo (sqlite ou mysql)
  Repository/TreeRepository.php    # CRUD arbres / nœuds / liens / conditions
  Repository/ProgressRepository.php# progression + métriques par sujet
  Service/ProgressEngine.php       # calcul des statuts (locked/available/completed), évaluation conditions
  Service/TreeValidator.php        # validation du payload de l'éditeur
  Service/Layout.php               # presets de positions (heart, radial, ring) côté PHP (seed)
  Http/ApiController.php           # dispatch des actions -> tableau réponse
  Http/Request.php                 # normalisation (action, params, body JSON)
  Http/Response.php                # JSON / fichier statique
  Schema/Installer.php             # CREATE TABLE IF NOT EXISTS via l'adaptateur (mysql|sqlite)
schema/mysql.sql                   # même schéma, à exécuter à la main si besoin
assets/successtree.js              # moteur de rendu + éditeur (vanilla)
assets/successtree.css
examples/native-php/               # intégration type : singleton maison + forceRequest
examples/demo/index.html           # démo statique (données JSON inline, sans backend)
examples/demo/heart-tree.json      # arbre de démonstration "cœur" (9 hubs + 1 central)
tests/run.php                      # tests PHP sans framework (sqlite en mémoire)
docs/INTEGRATION.md                # protocole d'intégration
.claude/skills/successtree-integration/SKILL.md   # skill IA d'intégration
```

## 3. Schéma BDD (préfixe configurable, défaut `st_`)

```sql
st_tree(
  uid VARCHAR(64) PK, slug VARCHAR(120) UNIQUE, name VARCHAR(190), description TEXT,
  layout VARCHAR(32) DEFAULT 'free',          -- free|heart|radial|ring
  theme TEXT NULL,                            -- JSON thème global
  settings TEXT NULL,                         -- JSON (expandPush, zoom, etc.)
  created_at DATETIME, updated_at DATETIME)

st_node(
  uid VARCHAR(64) PK, tree_uid VARCHAR(64) INDEX, parent_uid VARCHAR(64) NULL INDEX,
  kind VARCHAR(16),                           -- origin|hub|node
  slug VARCHAR(120) NULL, name VARCHAR(190), description TEXT NULL,
  icon VARCHAR(64) NULL,                      -- nom d'icône intégrée ou emoji/texte court
  pos_x DOUBLE NULL, pos_y DOUBLE NULL,       -- position explicite (unités monde, origine = 0,0 ; y vers le bas)
  theme TEXT NULL,                            -- JSON {color, glow, shape, size, label}
  reward TEXT NULL,                           -- JSON {xp, badge, ...} libre
  sort INT DEFAULT 0,
  meta TEXT NULL)                             -- JSON libre de l'hôte

st_link(
  uid VARCHAR(64) PK, tree_uid VARCHAR(64) INDEX,
  from_uid VARCHAR(64), to_uid VARCHAR(64),
  type VARCHAR(16) DEFAULT 'path')            -- path (visuel+prérequis) | visual (visuel seul)

st_condition(
  uid VARCHAR(64) PK, node_uid VARCHAR(64) INDEX,
  phase VARCHAR(16),                          -- unlock | complete
  type VARCHAR(32),                           -- voir §5
  params TEXT NULL,                           -- JSON
  sort INT DEFAULT 0)

st_progress(
  tree_uid VARCHAR(64), node_uid VARCHAR(64), subject VARCHAR(128),
  status VARCHAR(16),                         -- completed (seul statut persisté ; les autres sont calculés)
  completed_at DATETIME NULL, data TEXT NULL,
  PRIMARY KEY(node_uid, subject))

st_metric(
  subject VARCHAR(128), metric VARCHAR(120), value DOUBLE, updated_at DATETIME,
  PRIMARY KEY(subject, metric))
```

`parent_uid` = arbre hiérarchique visuel (branches). `st_link` = liaisons supplémentaires **sans limite**
(multi-parents, ponts entre branches, boucles visuelles). Un lien `parent_uid` implicite existe toujours
parent → enfant (type `path`).

## 4. Format JSON échangé (API ⇄ JS)

```json
{
  "tree":  {"uid":"t_love","slug":"love","name":"…","description":"…","layout":"heart",
            "theme":{"background":"#2b2d5c","accent":"#ff7a59","line":"rgba(255,255,255,.55)","font":"…"},
            "settings":{"expandPush":1.6,"hubRadius":34,"nodeRadius":9,"showLabels":"hover"}},
  "nodes": [{"uid":"n1","parent":null,"kind":"origin","slug":"start","name":"Départ","description":"…",
             "icon":"target","x":0,"y":0,"theme":{"color":"#ff5c8a"},"reward":{"xp":10},"sort":0,"meta":{},
             "conditions":[{"uid":"c1","phase":"unlock","type":"node","params":{"node":"n0"}}],
             "status":"available","progress":{"completed_at":null}}],
  "links": [{"uid":"l1","from":"n1","to":"n2","type":"path"}],
  "subject": "42",
  "metrics": {"posts_published": 12},
  "permissions": {"edit": true, "progress": false}
}
```

`x`/`y` peuvent être `null` → le JS calcule (auto-layout). Statuts calculés : `locked | available | completed`.

## 5. Conditions (`type` + `params`)

| type        | params                                   | vrai si |
|-------------|------------------------------------------|---------|
| `parent`    | `{}`                                     | le parent est complété (défaut implicite si le nœud n'a aucune condition unlock) |
| `node`      | `{"node":"uid"}`                         | ce nœud est complété |
| `all`       | `{"nodes":["uid",…]}`                    | tous complétés |
| `any`       | `{"nodes":["uid",…],"min":1}`            | au moins `min` complétés |
| `children`  | `{"min":null}`                           | tous (ou `min`) enfants complétés |
| `metric`    | `{"metric":"key","op":">=","value":10}`  | métrique du sujet satisfait l'opérateur (`> >= < <= == !=`) |
| `manual`    | `{}`                                     | jamais auto : validé par l'hôte/admin via API |
| `callback`  | `{"name":"has_paid"}`                    | callable hôte `config['conditions']['has_paid']($subject,$node,$params)` renvoie true |

Règles : l'`origin` est toujours `available`. Un nœud est `available` si toutes ses conditions `unlock` sont vraies
(sans condition unlock ⇒ condition `parent` implicite ; hub sans parent ⇒ dépend de l'origin). Il devient `completed`
soit via l'API `complete` (refusé s'il est `locked` ou si une condition `complete` est fausse), soit automatiquement
lors de `evaluate` si TOUTES ses conditions `complete` sont vraies, qu'il y en a au moins une et qu'aucune n'est `manual`.

## 6. API HTTP (une seule route hôte, paramètre `action`)

Requête : `GET|POST <route>?action=…&tree=<slug>`, corps JSON pour POST. Réponse : JSON
`{"ok":true,"data":…}` ou `{"ok":false,"error":"message"}` (HTTP 4xx).

| action     | méthode | permission | effet |
|------------|---------|------------|-------|
| `tree`     | GET     | —          | arbre complet + statuts pour le sujet courant (format §4) |
| `list`     | GET     | —          | liste des arbres `[{uid,slug,name,description,layout}]` |
| `complete` | POST    | progress   | `{"node":"uid"}` → marque complété, renvoie l'arbre recalculé |
| `uncomplete`| POST   | edit       | `{"node":"uid"}` |
| `metric`   | POST    | progress   | `{"metric":"key","value":12,"mode":"set|inc"}` puis évaluation auto |
| `evaluate` | POST    | —          | auto-complétions, renvoie l'arbre |
| `save`     | POST    | edit       | payload §4 complet (tree+nodes+links+conditions) → remplace l'arbre de façon transactionnelle-ish (delete+insert par tree_uid) |
| `delete`   | POST    | edit       | supprime l'arbre |
| `install`  | POST    | edit       | crée les tables |
| `asset`    | GET     | —          | `file=successtree.js|successtree.css` sert le fichier du paquet (cache headers) |

Configuration de la façade :
```php
new SuccessTree\SuccessTree($adapter, [
  'prefix'      => 'st_',
  'subject'     => (string)$userId,     // identifiant du joueur / utilisateur
  'can_edit'    => $isAdmin,            // bool
  'can_progress'=> false,               // autoriser le client à se marquer complété lui-même
  'conditions'  => ['has_paid' => function($subject,$node,$params){ return true; }],
  'on_complete' => function($subject,$node,$tree){ /* hook hôte : XP, notif… */ },
  'endpoint'    => '/ma-route',          // utilisé par renderMount()
  'driver'      => 'mysql',              // mysql|sqlite (DDL de l'installer)
]);
$response = $st->handle(SuccessTree\Http\Request::fromGlobals()); // -> Response
$response->send();                                              // headers + echo
// ou $st->handleArray($action, $params, $body) -> array pour les hôtes qui gèrent eux-mêmes la sortie
echo $st->renderMount('love', ['height'=>'80vh', 'edit'=>true]); // <div> + <link> + <script>
```

Adaptateur :
```php
interface DbAdapterInterface {
  public function select(string $sql): array;   // lignes assoc
  public function exec(string $sql): void;      // écriture
  public function quote($value): string;        // littéral SQL sûr, NULL/int/float/bool/string
  public function driver(): string;             // mysql|sqlite
}
new ForceRequestAdapter(Database::getInstance(), ['method'=>'forceRequest', 'escape'=>null /* callable optionnel */]);
```
`ForceRequestAdapter` normalise le retour de `forceRequest` : tableau de lignes, `mysqli_result`,
`PDOStatement`, objet itérable, `bool`/`int` (écriture), tableau d'objets stdClass. Échappement : callable fourni,
sinon `real_escape_string` si l'objet expose un mysqli (`getConnection()`/`$mysqli`…), sinon échappement MySQL
sûr (`\0 \n \r \\ ' " \x1a`) ; exige un charset utf8mb4 côté hôte.

## 7. Front-end (`assets/successtree.js`)

API globale `window.SuccessTree` :
```js
const view = SuccessTree.mount(el, {
  endpoint: '/ma-route',        // ou data: {...} (mode hors-ligne / démo)
  tree: 'love',
  edit: false,                  // mode constructeur
  theme: {...},                 // surcharge
  onNodeClick(node, view) {}, onComplete(node) {}, onSave(payload) {},
  i18n: {...}
});
view.reload(); view.focus(uid); view.expand(uid); view.collapse(); view.getData(); view.destroy();
```
Montage automatique : tout `<div data-successtree data-endpoint="…" data-tree="…" data-edit="1">`.

Rendu : SVG (liens, nœuds, halos), fond étoilé, pan (drag) + zoom (molette/pinch), labels.
- **Hubs** (kind=hub) : gros cercles avec icône, halo coloré ; **branches** (kind=node) : petits points reliés,
  poussent vers l'extérieur depuis le centre (angle hub→origine inversé), éventail des enfants, profondeur libre.
- **Expansion** : clic sur un hub → zoom/focus dessus, ses nœuds s'agrandissent ; si c'est le hub central (posé sur
  l'origin), il se déploie en cercle et **pousse les autres hubs vers l'extérieur** (facteur `settings.expandPush`),
  animation fluide (requestAnimationFrame, easing).
- Statuts : `locked` (grisé, pointillé), `available` (pulse), `completed` (plein + lueur accent).
- Panneau latéral de détail : nom, description, conditions lisibles, récompense, bouton « Valider » si permis.
- **Mode édition** : drag des nœuds (écrit x/y), ajout enfant, suppression, liaison entre deux nœuds (shift+clic),
  panneau propriétés (nom, slug, description, icône, couleur, kind, conditions JSON, reward JSON, meta JSON),
  preset de layout (bouton « Cœur »), bouton Sauvegarder → POST `save`. Export/Import JSON.
- Icônes : jeu intégré d'icônes SVG en ligne (target, heart, star, list, users, filter, database, globe, chart,
  building, check, layers, bolt, book, flag, crown, gem, shield, leaf, flame, lock) ; sinon texte/emoji.
- Thème via variables CSS `--st-*` + `tree.theme` + `node.theme`.
- Aucune dépendance, aucun `eval`, textes insérés via `textContent` (anti-XSS).

## 8. Preset « cœur » (9 hubs + 1 central)

Courbe paramétrique du cœur `x = 16 sin³t`, `y = -(13 cos t − 5 cos 2t − 2 cos 3t − cos 4t)`, normalisée
et mise à l'échelle `R` (défaut 420 unités). 9 hubs à t = π + 2πk/9 (k = 0..8) : un hub dans la pointe basse (t = π) puis 4 paires
symétriques gauche/droite (les lobes hauts encadrent le creux, qui reste vide). Le 10ᵉ hub
(central) posé en (0,0) sur l'origin (légèrement décalé si besoin pour être cliquable : hub central
au-dessus de l'origin, l'origin est son point d'ancrage). Implémenté à l'identique en PHP (`Layout::heart`)
et JS (`SuccessTree.layouts.heart`).
