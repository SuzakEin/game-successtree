# SuccessTree — Protocole d'intégration

Ce document décrit, étape par étape, comment brancher SuccessTree sur **n'importe quel projet PHP** (natif ou
framework) sans toucher à son code existant. Chaque étape se termine par une **vérification** : ne passez à la
suivante que lorsqu'elle est verte.

Le contrat technique de référence est [`docs/SPEC.md`](SPEC.md). En cas de doute, la SPEC fait foi ; ce document
la reprend sans la contredire.

```
Navigateur ──► route hôte (/arbre/api) ──► VOTRE contrôleur ──► SuccessTree::handle()
                                              │                       │
                                              │ auth, subject,        ├─► ApiController ─► Repositories ─► Adapter ─► Database::forceRequest()
                                              │ can_edit              └─► Response (JSON ou asset)
                                              ▼
                                         route hôte (/arbre) ──► vue hôte ──► renderMount() ──► successtree.js
```

Principes (SPEC §1) : **l'hôte garde la main** (routing, session, auth) ; SuccessTree ne demande qu'un objet
exposant `forceRequest(string $sql)` ; toutes les valeurs sont échappées par l'adaptateur ; les identifiants sont
des chaînes `uid` générées côté application (pas d'auto-incrément).

---

## Sommaire

1. [Prérequis](#1-prérequis)
2. [Copier le paquet](#2-copier-le-paquet)
3. [Charger l'autoloader](#3-charger-lautoloader)
4. [Créer l'adaptateur BDD](#4-créer-ladaptateur-bdd)
5. [Installer les tables](#5-installer-les-tables)
6. [Créer le contrôleur hôte et la route](#6-créer-le-contrôleur-hôte-et-la-route)
7. [Afficher la vue](#7-afficher-la-vue)
8. [Charger l'arbre démo « cœur »](#8-charger-larbre-démo-cœur)
9. [Déclencher la progression depuis le code métier](#9-déclencher-la-progression-depuis-le-code-métier)
10. [Personnalisation visuelle](#10-personnalisation-visuelle)
11. [Checklist de recette](#11-checklist-de-recette)
12. [Dépannage](#12-dépannage)
13. [Référence : actions API](#13-référence--actions-api)
14. [Référence : format JSON](#14-référence--format-json)
15. [Référence : conditions](#15-référence--conditions)
16. [Référence : front-end JavaScript](#16-référence--front-end-javascript)

---

## 1. Prérequis

| Élément | Exigence |
|---|---|
| PHP | **≥ 7.4** (compatible 8.x), extension `json` ; `mysqli` ou `pdo_mysql` selon votre singleton |
| Base | **MySQL ≥ 5.7 / MariaDB ≥ 10.3**, jeu de caractères **utf8mb4** (base, tables et connexion) |
| Accès BDD | un objet (souvent un singleton `Database::getInstance()`) exposant une méthode qui exécute du SQL brut, par défaut `forceRequest($sql)` |
| Front | navigateur moderne (ES2017) ; aucun build, aucune dépendance npm |
| Droits | pouvoir créer 6 tables (préfixe `st_` par défaut) |

**Vérification**

```bash
php -v                                   # ≥ 7.4
php -m | grep -Ei 'json|mysqli|pdo_mysql'
mysql -e "SELECT @@character_set_server, @@collation_server"
```

Dans votre singleton, la connexion doit être en utf8mb4 : `$mysqli->set_charset('utf8mb4')` ou DSN PDO
`...;charset=utf8mb4`. Sans cela, accents et emojis seront cassés **et** l'échappement moins sûr.

---

## 2. Copier le paquet

Au choix :

```bash
# a) copie simple
cp -r game-successtree /chemin/projet/vendor-local/successtree

# b) sous-module git (mises à jour faciles)
cd /chemin/projet
git submodule add <url-du-depot> vendor-local/successtree
git submodule update --init
```

Seuls `src/` (PHP), `assets/` (JS/CSS) et `schema/` (SQL) sont nécessaires en production. Les assets n'ont pas
besoin d'être dans le dossier public : SuccessTree peut les servir lui-même via l'action `asset` (étape 7).

**Vérification** : `ls vendor-local/successtree/src/autoload.php vendor-local/successtree/assets/successtree.js`.

---

## 3. Charger l'autoloader

Dans votre bootstrap (ou juste dans le contrôleur qui utilisera SuccessTree) :

```php
require_once __DIR__ . '/vendor-local/successtree/src/autoload.php';
```

L'autoloader PSR-4 ne gère que l'espace de noms `SuccessTree\` : il n'interfère pas avec le vôtre ni avec Composer.

**Vérification** : `php -r "require 'vendor-local/successtree/src/autoload.php'; var_dump(class_exists('SuccessTree\SuccessTree'));"` → `bool(true)`.

---

## 4. Créer l'adaptateur BDD

SuccessTree ne parle à votre base qu'à travers `SuccessTree\Db\DbAdapterInterface` (`select`, `exec`, `quote`,
`driver`). Pour un singleton maison, utilisez `ForceRequestAdapter` :

```php
use SuccessTree\Db\ForceRequestAdapter;

$db = Database::getInstance();

$adapter = new ForceRequestAdapter($db, [
    'method' => 'forceRequest',                             // nom de la méthode SQL brute de l'hôte
    'escape' => [$db->getConnection(), 'real_escape_string'], // RECOMMANDÉ si le singleton expose sa connexion
    'driver' => 'mysql',                                    // mysql | sqlite
]);
```

| Option | Défaut | Rôle |
|---|---|---|
| `method` | `'forceRequest'` | méthode appelée avec la chaîne SQL. Mettez `'query'`, `'rawQuery'`… si votre singleton la nomme autrement |
| `escape` | `null` | callable `fn(string $brut): string` qui renvoie la valeur **échappée sans guillemets** ; l'adaptateur ajoute les `'…'` |
| `driver` | `'mysql'` (auto `sqlite` si un PDO sqlite est détecté) | choisit le dialecte SQL (DDL de l'installer notamment) |

### Stratégie d'échappement (dans l'ordre)

1. **Callable `escape` fourni** → utilisé tel quel. C'est la meilleure option : reliez-le au
   `real_escape_string` de **la même connexion** que `forceRequest` (il tient compte du charset réel).
2. Sinon, détection automatique d'une connexion exposée par le singleton : méthodes `getConnection()`,
   `getMysqli()`, `getLink()`, `getPdo()`, `getDb()` ou propriétés publiques `$mysqli`, `$connection`, `$link`,
   `$db`, `$pdo` → `mysqli::real_escape_string` ou `PDO::quote`.
3. Sinon, échappement MySQL manuel sûr (`\0 \n \r \\ ' " \x1a`) — **correct uniquement si la connexion est en utf8mb4**.

Les entiers, flottants, booléens et `NULL` sont rendus en littéraux SQL sans passer par l'échappement de chaîne.
Aucune valeur n'est jamais concaténée brute dans le SQL.

### Retours de `forceRequest` acceptés

Tableau de lignes associatives, tableau d'objets `stdClass`, une seule ligne associative, `mysqli_result`,
`PDOStatement`, tout `Traversable`, `bool`/`int`/`null` pour les écritures. **`false` sur une écriture est traité
comme une erreur** (exception). Votre singleton n'a donc en général **rien à modifier**.

### Variante PDO

Si votre projet utilise PDO directement, `SuccessTree\Db\PdoAdapter` est fourni (même interface). Vous pouvez aussi
écrire votre propre classe implémentant `DbAdapterInterface`.

**Vérification**

```php
var_dump($adapter->select('SELECT 1 AS un'));   // [['un' => '1']] (ou 1)
var_dump($adapter->quote("l'été"));             // 'l\'été'
var_dump($adapter->escapeMode());               // callable | mysqli | pdo | manual  (ForceRequestAdapter)
```

---

## 5. Installer les tables

Deux méthodes équivalentes (tables `st_tree`, `st_node`, `st_link`, `st_condition`, `st_progress`, `st_metric`,
voir SPEC §3). `CREATE TABLE IF NOT EXISTS` : relançable sans risque.

**a) Par l'API (depuis PHP, sans passer par HTTP)**

```php
$st = new SuccessTree\SuccessTree($adapter, ['can_edit' => true, 'driver' => 'mysql']);
$res = $st->handleArray('install', [], []);   // ['ok' => true, ...]
```

Ou en HTTP, connecté en admin : `POST /arbre/api?action=install`.

**b) À la main**

```bash
mysql -u USER -p MA_BASE < vendor-local/successtree/schema/mysql.sql
```

Si vous changez le préfixe (`'prefix' => 'jeu_'`), remplacez `st_` dans le fichier SQL avant de l'exécuter.

**Vérification** : `SHOW TABLES LIKE 'st\_%';` → 6 tables.

---

## 6. Créer le contrôleur hôte et la route

Choisissez **une** route pour l'API (ex. `/arbre/api`) et une route de page (ex. `/arbre`). Votre routeur appelle
votre contrôleur, qui construit la façade avec les informations d'authentification **de votre projet** :

| Clé de config | Valeur à donner | Remarque |
|---|---|---|
| `subject` | `(string)$userId` | identifiant du joueur ; toute la progression et les métriques sont rangées par sujet |
| `can_edit` | `$user->isAdmin()` | autorise `save`, `delete`, `install`, `uncomplete` (mode Construire) **et implique la permission progress** (un admin peut valider) |
| `can_progress` | `false` côté navigateur | `true` seulement si l'utilisateur peut se valider lui-même (`complete`, `metric`) |
| `endpoint` | `'/arbre/api'` | URL utilisée par `renderMount()` et le JS |
| `prefix` | `'st_'` | préfixe des tables |
| `driver` | `'mysql'` | dialecte de l'installer |
| `conditions` | `['nom' => callable]` | conditions `callback` (étape 9) |
| `on_complete` | `callable($subject, $node, $tree)` | hook appelé à chaque complétion |

Exemple complet (PHP natif, voir aussi `examples/native-php/`) :

```php
<?php
// controllers/SuccessTreeController.php
use SuccessTree\Db\ForceRequestAdapter;
use SuccessTree\Http\Request;
use SuccessTree\SuccessTree;

final class SuccessTreeController
{
    public function api(): void
    {
        $this->successTree()->handle(Request::fromGlobals())->send();
    }

    public function page(): void
    {
        $user  = Auth::user();                             // votre gestion d'auth
        $mount = $this->successTree()->renderMount('coeur', [
            'height' => '80vh',
            'edit'   => $user->isAdmin(),
        ]);
        require __DIR__ . '/../views/successtree.php';     // votre layout
    }

    private function successTree(): SuccessTree
    {
        $user = Auth::user();
        $db   = Database::getInstance();

        return new SuccessTree(
            new ForceRequestAdapter($db, ['escape' => [$db->getConnection(), 'real_escape_string']]),
            [
                'prefix'       => 'st_',
                'subject'      => (string)$user->id,
                'can_edit'     => $user->isAdmin(),
                'can_progress' => false,
                'endpoint'     => '/arbre/api',
                'driver'       => 'mysql',
                'conditions'   => [
                    'has_paid' => function ($subject, $node, $params) { return Billing::isPaid((int)$subject); },
                ],
                'on_complete'  => function ($subject, $node, $tree) { Xp::grant((int)$subject, $node['reward']['xp'] ?? 0); },
            ]
        );
    }
}
```

Routage (exemples) :

```php
// Front-controller natif
switch (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
    case '/arbre':     (new SuccessTreeController())->page(); break;
    case '/arbre/api': (new SuccessTreeController())->api();  break;
}
```

```php
// Hôte qui gère lui-même la sortie (framework, middleware JSON…)
$result = $st->handleArray($_GET['action'] ?? 'tree', $_GET, json_decode(file_get_contents('php://input'), true) ?: []);
// $result = ['ok' => true, 'data' => …] ou ['ok' => false, 'error' => '…'] → à sérialiser par l'hôte
```

**Règles** : protégez la route par votre middleware d'auth si l'arbre est privé ; n'exposez jamais `can_edit = true`
à un utilisateur non administrateur ; ne passez jamais un `subject` venant de la requête (toujours de la session).

**Vérification**

```bash
curl -s 'http://localhost:8000/arbre/api?action=list'         # {"ok":true,"data":[…]}
curl -s -X POST 'http://localhost:8000/arbre/api?action=save'  # non admin → HTTP 403 {"ok":false,…}
```

---

## 7. Afficher la vue

**a) `renderMount()` (recommandé)** : génère le `<div data-successtree …>` ainsi que les balises `<link>` et
`<script>` des assets, servis par votre route via l'action `asset`.

```php
<!-- views/successtree.php -->
<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><title>Mon arbre</title></head>
<body>
  <?= $mount /* $st->renderMount('coeur', ['height' => '80vh', 'edit' => $isAdmin]) */ ?>
</body>
</html>
```

Options de `renderMount($slug, $opts)` : `height` (ex. `80vh`, `600px`), `edit` (pris en compte seulement si
`can_edit`), `endpoint` (surcharge), `assets_url` (dossier public contenant `successtree.js/.css` au lieu de
l'action `asset`), `assets` (`false` pour n'émettre que le `<div>`), `id`, `class`.

**b) Balise manuelle** : si vous préférez servir `assets/` depuis votre dossier public (CDN, cache, CSP) :

```html
<link rel="stylesheet" href="/assets/successtree/successtree.css">
<div data-successtree data-endpoint="/arbre/api" data-tree="coeur" data-edit="0" style="height:80vh"></div>
<script src="/assets/successtree/successtree.js" defer></script>
```

Ou via la route : `/arbre/api?action=asset&file=successtree.js` et `…&file=successtree.css`.

**c) Montage JavaScript** (contrôle total) : `SuccessTree.mount(el, {endpoint: '/arbre/api', tree: 'coeur'})`
(voir §16).

Le conteneur doit avoir une **hauteur** (ex. `80vh`) : le rendu SVG occupe 100 % du bloc.

**Vérification** : la page affiche un fond étoilé ; l'onglet Réseau montre `action=tree` en 200. Tant qu'aucun
arbre n'existe, l'API renvoie une erreur « arbre introuvable » : passez à l'étape 8.

---

## 8. Charger l'arbre démo « cœur »

Le paquet fournit un arbre prêt à l'emploi : origin « Départ », 9 hubs disposés en cœur (preset SPEC §8, R = 420)
et un hub central (`meta.central = true`) posé au-dessus de l'origin, qui pousse les autres une fois déployé.

**a) En PHP (script de seed, CLI)**

```php
use SuccessTree\Seed\HeartDemo;

$st = new SuccessTree\SuccessTree($adapter, ['can_edit' => true, 'subject' => 'seed']);
$st->handleArray('install', [], []);
$payload = HeartDemo::payload();
$payload['tree']['slug'] = 'coeur';                      // slug de votre choix (défaut : heart-demo)
$res = $st->handleArray('save', ['tree' => 'coeur'], $payload);
```

Alternative équivalente : appeler directement le repository (`SuccessTree\Repository\TreeRepository`) si vous
l'instanciez vous-même — l'action `save` reste préférable car elle passe par `TreeValidator`.

**b) En HTTP** (admin connecté) : `POST /arbre/api?action=save` avec pour corps le JSON de
`examples/demo/heart-tree.json` (même esprit, thème entreprise).

**c) Depuis l'éditeur** : mode Construire → bouton « Cœur » (preset de layout) → Sauvegarder.

**Vérification** : `curl -s '…/arbre/api?action=tree&tree=coeur'` renvoie 1 nœud `origin`, 10 nœuds `hub` dont un
avec `meta.central = true`, et des statuts calculés (`available` pour l'origin).

---

## 9. Déclencher la progression depuis le code métier

La progression est **pilotée par le serveur**. Dans le code métier de l'hôte, construisez une instance
« de confiance » pour le sujet concerné (`can_progress = true`), puis appelez l'action voulue :

```php
function successTreeFor(string $userId): SuccessTree\SuccessTree {
    $db = Database::getInstance();
    return new SuccessTree\SuccessTree(
        new SuccessTree\Db\ForceRequestAdapter($db, ['escape' => [$db->getConnection(), 'real_escape_string']]),
        ['subject' => $userId, 'can_progress' => true,
         'conditions' => hostSuccessTreeConditions(),          // même tableau que dans le contrôleur
         'on_complete' => 'onSuccessTreeComplete']
    );
}

// 1. Métriques : après chaque événement métier (article publié, vente signée…)
successTreeFor((string)$user->id)->handleArray('metric', ['tree' => 'coeur'],
    ['metric' => 'posts_published', 'value' => 1, 'mode' => 'inc']);   // ou 'set' avec une valeur absolue
// → la métrique est enregistrée puis `evaluate` complète automatiquement les nœuds dont TOUTES les
//   conditions `complete` sont vraies (au moins une, aucune `manual`).

// 2. Validation directe d'un nœud (ex. un admin valide une étape `manual`)
successTreeFor($userId)->handleArray('complete', ['tree' => 'coeur'], ['node' => 'ent_n_ventes_2_4']);
// refusé si le nœud est `locked` ou si une condition `complete` non-manuelle est fausse

// 3. Réévaluer après un changement externe (paiement reçu → condition callback)
successTreeFor($userId)->handleArray('evaluate', ['tree' => 'coeur'], []);
```

Raccourcis serveur équivalents (méthodes de la façade, **sans contrôle de permission** : à n'appeler que depuis
votre code) : `$st->metric('posts_published', 1, 'inc', $userId)` (réévalue tous les arbres),
`$st->complete('coeur', 'ent_n_ventes_2_4', $userId)` (lève `\DomainException` `node_locked` / `conditions_not_met`),
`$st->evaluate('coeur', $userId)`, `$st->tree('coeur', $userId)`, `$st->install()`.

**Hook `on_complete`** : `function ($subject, $node, $tree)` est appelé à chaque complétion (API ou
auto-évaluation) : attribuez de l'XP, envoyez une notification, journalisez. Le hook ne doit pas lever
d'exception bloquante.

**Conditions `callback`** : déclarez dans la config `'conditions' => ['has_paid' => callable]`. Le callable reçoit
`($subject, $node, $params)` et renvoie un booléen. Il doit être **rapide et sans effet de bord** (il est appelé à
chaque calcul de statut). Côté navigateur en mode hors-ligne (`data:`), une condition `callback` ne peut pas être
évaluée : elle vaut faux.

**Vérification** : incrémentez une métrique jusqu'au seuil d'une condition `metric` ; `action=tree` renvoie ce nœud
en `completed` et votre hook a été appelé (log).

---

## 10. Personnalisation visuelle

Trois niveaux, du plus global au plus précis :

1. **Variables CSS `--st-*`** (feuille de l'hôte, s'applique à toutes les vues) :

   ```css
   [data-successtree] {
     --st-bg: #2e2f6b;          /* fond */
     --st-accent: #c8b6ff;      /* complétés, halos */
     --st-line: rgba(255,255,255,.55);
     --st-font: system-ui, sans-serif;
   }
   ```

   La liste exhaustive des variables est documentée en tête de `assets/successtree.css`.

2. **`tree.theme`** (par arbre, enregistré en base) : `background`, `accent`, `line`, `font`.
3. **`node.theme`** (par nœud) : `color`, `glow`, `shape`, `size`, `label`.

**`tree.settings`** :

| Réglage | Exemple | Effet |
|---|---|---|
| `expandPush` | `1.6` | facteur de poussée des hubs quand le hub central se déploie |
| `hubRadius` | `34` | rayon des hubs (unités monde) |
| `nodeRadius` | `9` | rayon des points de branche |
| `showLabels` | `"hover"` | affichage des libellés (ex. `"hover"`, `"always"`, `"never"` — voir `successtree.js`) |

**Icônes intégrées** (`node.icon`) : `target, heart, star, list, users, filter, database, globe, chart, building,
check, layers, bolt, book, flag, crown, gem, shield, leaf, flame, lock` ; sinon un texte court ou un emoji.

**Surcharge JS ponctuelle** : `SuccessTree.mount(el, {theme: {...}, i18n: {...}})`.

---

## 11. Checklist de recette

- [ ] `php -v` ≥ 7.4, connexion en utf8mb4
- [ ] `require src/autoload.php` → `class_exists('SuccessTree\SuccessTree')` vrai
- [ ] `$adapter->select('SELECT 1')` renvoie une ligne ; `escapeMode()` ≠ `manual` (ou connexion utf8mb4 confirmée)
- [ ] 6 tables `st_*` présentes
- [ ] `GET ?action=list` → `{"ok":true}`
- [ ] `GET ?action=tree&tree=coeur` → 1 origin, 10 hubs, 1 hub `meta.central`
- [ ] `POST ?action=save` en non-admin → **403**
- [ ] `POST ?action=complete` par un joueur non admin avec `can_progress=false` → **403**
- [ ] `GET ?action=asset&file=successtree.js` → 200, `Content-Type` JavaScript
- [ ] La page affiche le cœur ; pan (glisser) et zoom (molette / pincement) fonctionnent
- [ ] Clic sur le hub central → il se déploie et les autres hubs s'écartent en douceur
- [ ] Le panneau de détail affiche nom, description, conditions lisibles, récompense
- [ ] Une métrique incrémentée depuis le code métier complète le nœud attendu ; `on_complete` est appelé
- [ ] Admin : mode Construire → déplacer un nœud, ajouter un enfant, lier deux nœuds (shift+clic), Sauvegarder, recharger : les changements persistent
- [ ] Les accents (« Équipe », « Opérations ») et un emoji s'affichent correctement après sauvegarde
- [ ] Aucun message d'erreur dans la console navigateur ni dans les logs PHP

---

## 12. Dépannage

| Symptôme | Cause probable | Solution |
|---|---|---|
| `Host query failed` / arbre vide alors que les données existent | `forceRequest` renvoie un format inattendu (ressource, objet maison, JSON…) | Vérifiez `var_dump($db->forceRequest('SELECT 1 AS un'))`. Formats acceptés : §4. Sinon, écrivez un petit adaptateur implémentant `DbAdapterInterface` qui convertit en tableau de lignes — **sans modifier votre DAO** |
| `Write query failed` | `forceRequest` renvoie `false` : erreur SQL | Consultez le log de votre singleton ; vérifiez les droits `CREATE`/`INSERT` et que les tables existent (étape 5) |
| `Host object has no callable method forceRequest()` | méthode nommée autrement ou privée | Option `'method' => 'nomReel'` |
| HTTP **403** | action protégée : `can_edit` / `can_progress` à `false` | Normal pour un joueur. Pour un admin, vérifiez que votre contrôleur passe bien `can_edit => true` (session chargée *avant* la construction de la façade) |
| HTTP 404 sur l'API | route non déclarée, ou `endpoint` différent de la route réelle | Alignez `endpoint` sur l'URL exacte (préfixe de sous-dossier compris) |
| Assets **404** / `SuccessTree is not defined` | `renderMount()` pointe vers `endpoint?action=asset` mais la route n'est pas accessible, ou chemin manuel faux | Testez `…/arbre/api?action=asset&file=successtree.js` ; en balise manuelle, vérifiez le chemin public |
| Accents cassés (`Ã©`, `?`) | connexion non utf8mb4 ou tables créées en latin1 | `set_charset('utf8mb4')` dans le singleton ; `ALTER DATABASE … CHARACTER SET utf8mb4` ; recréer les tables si besoin ; fichiers PHP en UTF-8 sans BOM |
| `{"ok":false,"error":"Internal error"}` (500) | exception interne masquée | Ajoutez temporairement `'debug' => true` à la config de la façade pour obtenir le message, puis retirez-le |
| HTTP 405 | mauvaise méthode (ex. `save` en GET) | Les actions d'écriture exigent POST (§13) |
| `tree.uid does not match the existing tree` / `uid … already used by another tree` | les `uid` sont des clés primaires globales | Gardez le `tree.uid` d'un arbre existant ; préfixez les uid de chaque arbre (`ent_…`) |
| Page blanche ou JSON corrompu | sortie parasite (BOM, `echo`, notice PHP) avant `send()` | Désactivez `display_errors` sur la route API, supprimez les BOM, ne rien écrire avant `->send()` |
| Blocage **CSP** | politique interdisant les scripts/styles de la route ou le style inline de `renderMount` | Servez les assets depuis une origine autorisée (balise manuelle, variante b), ajoutez la route à `script-src`/`style-src`, ou fixez la hauteur du conteneur dans votre CSS. SuccessTree n'utilise ni `eval` ni `new Function` |
| Le hub central ne pousse pas les autres | nœud sans `meta.central = true`, ou `settings.expandPush` ≤ 1 | Corrigez le payload puis `save` |
| Un nœud reste verrouillé | condition `unlock` non satisfaite (souvent : origin non complété) | Lisez le panneau de détail ; complétez l'origin « Départ » en premier |
| Condition `callback` toujours fausse | nom absent de `config['conditions']`, ou mode hors-ligne JS | Déclarez le callable ; en démo statique c'est attendu |

---

## 13. Référence : actions API

Requête : `GET|POST <route>?action=…&tree=<slug>`, corps JSON pour POST.
Réponse : `{"ok":true,"data":…}` ou `{"ok":false,"error":"message"}` (HTTP 4xx).

| action | méthode | permission | corps / paramètres | effet |
|---|---|---|---|---|
| `tree` | GET | — | `tree` | arbre complet + statuts pour le sujet courant (format §14) |
| `list` | GET | — | — | `[{uid, slug, name, description, layout}]` |
| `complete` | POST | progress | `{"node":"uid"}` | marque complété, renvoie l'arbre recalculé |
| `uncomplete` | POST | edit | `{"node":"uid"}` | annule une complétion |
| `metric` | POST | progress | `{"metric":"key","value":12,"mode":"set\|inc"}` | enregistre la métrique puis évaluation auto |
| `evaluate` | POST | — | `tree` | auto-complétions, renvoie l'arbre |
| `save` | POST | edit | payload §14 complet | remplace l'arbre (delete + insert par `tree_uid`) |
| `delete` | POST | edit | `tree` | supprime l'arbre |
| `install` | POST | edit | — | crée les tables |
| `asset` | GET | — | `file=successtree.js\|successtree.css` | sert le fichier du paquet (en-têtes de cache) |

Façade PHP :

```php
$st = new SuccessTree\SuccessTree($adapter, [
  'prefix' => 'st_', 'subject' => (string)$userId, 'can_edit' => $isAdmin, 'can_progress' => false,
  'conditions' => ['has_paid' => function ($subject, $node, $params) { return true; }],
  'on_complete' => function ($subject, $node, $tree) {},
  'endpoint' => '/ma-route', 'driver' => 'mysql',
]);
$st->handle(SuccessTree\Http\Request::fromGlobals())->send();   // Response : headers + echo
$st->handleArray($action, $params, $body);                      // ['ok'=>true,'data'=>…] | ['ok'=>false,'error'=>…,'status'=>4xx] ; méthode HTTP non vérifiée (4e argument $method pour l'imposer)
echo $st->renderMount('coeur', ['height' => '80vh', 'edit' => true]);
```

---

## 14. Référence : format JSON

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

| Champ | Règle |
|---|---|
| `uid` | chaîne ≤ 64 caractères, **unique dans toute la base** (clé primaire partagée par tous les arbres : préfixez par arbre) ; générée côté application (jamais d'auto-incrément) |
| `tree.layout` | `free` \| `heart` \| `radial` \| `ring` |
| `nodes[].kind` | `origin` (exactement **un** par arbre) \| `hub` \| `node` |
| `nodes[].parent` | `uid` d'un nœud existant ou `null` ; définit la branche visuelle et un lien `path` implicite parent → enfant |
| `nodes[].x`, `y` | unités monde, origine (0,0), **y vers le bas** ; `null` → auto-layout JS |
| `nodes[].theme` | `{color, glow, shape, size, label}` ; `reward` et `meta` : JSON libre |
| `nodes[].status` | calculé : `locked` \| `available` \| `completed` (seul `completed` est persisté) ; inutile dans un `save` |
| `links[].type` | `path` (visuel + prérequis : s'ajoute au parent implicite) \| `visual` (visuel seul) ; nombre illimité, ponts et multi-parents autorisés |
| `meta.central` | `true` sur le hub central posé sur l'origin (déploiement + poussée) |

---

## 15. Référence : conditions

| type | params | vrai si |
|---|---|---|
| `parent` | `{}` | le parent est complété (défaut implicite si le nœud n'a aucune condition unlock) |
| `node` | `{"node":"uid"}` | ce nœud est complété |
| `all` | `{"nodes":["uid",…]}` | tous complétés |
| `any` | `{"nodes":["uid",…],"min":1}` | au moins `min` complétés |
| `children` | `{"min":null}` | tous (ou `min`) enfants complétés |
| `metric` | `{"metric":"key","op":">=","value":10}` | la métrique du sujet satisfait l'opérateur (`> >= < <= == !=`) |
| `manual` | `{}` | jamais automatique : validé par l'hôte/admin via l'API |
| `callback` | `{"name":"has_paid"}` | `config['conditions']['has_paid']($subject, $node, $params)` renvoie `true` |

Chaque condition a une `phase` : `unlock` (rend le nœud `available`) ou `complete` (nécessaire pour le compléter).

Règles de statut :
- l'`origin` est toujours `available` ;
- un nœud est `available` si toutes ses conditions `unlock` sont vraies ; sans condition `unlock` ⇒ condition
  `parent` implicite ; un hub sans parent dépend de l'origin ;
- une condition `unlock` explicite **remplace** le parent implicite (parent + sources des liens `path`) : pour exiger
  aussi le parent, incluez-le dans un `all` ;
- `children` n'est jamais vrai pour un nœud sans enfant ; évitez-le en phase `complete` sur un nœud dont les enfants
  attendent le parent (interblocage) ;
- il devient `completed` via l'API `complete` (refusé s'il est `locked` ou si une condition `complete` est fausse),
  ou automatiquement lors de `evaluate` si **toutes** ses conditions `complete` sont vraies, qu'il y en a au moins
  une et qu'aucune n'est `manual`.

---

## 16. Référence : front-end JavaScript

```js
const view = SuccessTree.mount(el, {
  endpoint: '/arbre/api',   // ou data: {...} (mode hors-ligne / démo)
  tree: 'coeur',
  edit: false,              // mode constructeur
  theme: {...},             // surcharge
  onNodeClick(node, view) {}, onComplete(node) {}, onSave(payload) {},
  i18n: {...}
});
view.reload(); view.focus(uid); view.expand(uid); view.collapse(); view.getData(); view.destroy();
```

Montage automatique : tout `<div data-successtree data-endpoint="…" data-tree="…" data-edit="1">`.

Rendu SVG (fond étoilé, pan, zoom, labels) ; hubs en gros cercles avec icône et halo ; branches en points poussés
vers l'extérieur ; statuts `locked` (grisé, pointillé), `available` (pulsation), `completed` (plein + lueur).
Mode édition : glisser les nœuds, ajouter un enfant, supprimer, lier (shift+clic), panneau de propriétés,
preset « Cœur », Sauvegarder (POST `save`), export/import JSON. Textes insérés via `textContent` (anti-XSS).
