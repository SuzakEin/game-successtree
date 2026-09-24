# game-successtree — SuccessTree

**Constructeur d'arbres de progression gamifiés, branchable sur n'importe quel projet PHP.**
Dessinez une constellation, un sphérier, un arbre de politiques ou un cœur de neuf domaines ; vos utilisateurs
débloquent les étapes au fil de leurs actions réelles. Zéro dépendance : PHP natif ≥ 7.4, JavaScript vanilla, CSS pur.

## L'intention en images

- **Constellation map** : fond bleu-violet étoilé, gros hubs lavande avec icône, branches faites de petits points
  lumineux reliés par des traits fins.
- **Cœur 9 + 1** : neuf hubs dessinant un cœur (la pointe en bas, deux lobes en haut) et un hub central posé sur le
  départ ; quand on le déploie, il s'ouvre en cercle et **repousse doucement les autres hubs** vers l'extérieur.
- **Sphérier FFX** : réseau libre, boucles, verrous entre zones.
- **Arbre des politiques Civ V** : colonnes verticales, prérequis croisés, accomplissement de branche.
- **Materia FF7 / capacités FF9** : des points qui se remplissent au fur et à mesure qu'une métrique progresse.

## Fonctionnalités

- Rendu SVG : fond étoilé, pan et zoom (souris, molette, pincement), halos, animations fluides.
- Hubs, branches de profondeur libre, liens supplémentaires illimités (ponts, multi-parents, boucles visuelles).
- Statuts calculés `locked` / `available` / `completed` et conditions riches : parent, nœud, `all`, `any`,
  enfants, métrique chiffrée, validation manuelle, callback métier de l'hôte.
- Mode **Constructeur** : glisser-déposer, ajout d'enfants, liaisons (shift+clic), panneau de propriétés,
  preset « Cœur », export / import JSON, sauvegarde.
- Thèmes par arbre et par nœud, variables CSS `--st-*`, icônes SVG intégrées.
- **Intégration non intrusive** : l'hôte garde son routing, sa session et son auth ; il fournit simplement son
  singleton BDD (`forceRequest($sql)`) ou un PDO. Valeurs toujours échappées, identifiants `uid` générés côté appli.
- Une seule route API (`?action=tree|list|complete|uncomplete|metric|evaluate|save|delete|install|asset`).
- Hook `on_complete` pour l'XP, les notifications, les badges.

## Démarrage rapide

```php
require 'successtree/src/autoload.php';
$db = Database::getInstance();
$st = new SuccessTree\SuccessTree(new SuccessTree\Db\ForceRequestAdapter($db), ['subject' => (string)$userId, 'can_edit' => $isAdmin, 'endpoint' => '/arbre/api']);
if (strtok($_SERVER['REQUEST_URI'], '?') === '/arbre/api') { $st->handle(SuccessTree\Http\Request::fromGlobals())->send(); exit; }
echo $st->renderMount('coeur', ['height' => '80vh', 'edit' => $isAdmin]);
```

Créez ensuite les tables
(`action=install` ou `schema/mysql.sql`) et chargez l'arbre démo (`SuccessTree\Seed\HeartDemo::payload()`).

Démo statique sans backend : `php -S localhost:8000` à la racine, puis http://localhost:8000/examples/demo/.

## Documentation

| Document | Contenu |
|---|---|
| [`docs/INTEGRATION.md`](docs/INTEGRATION.md) | **protocole d'intégration** pas-à-pas, checklist de recette, dépannage, références API / JSON / conditions |
| [`docs/SPEC.md`](docs/SPEC.md) | contrat technique (schéma BDD, format JSON, API, front, preset cœur) |
| [`.claude/skills/successtree-integration/`](.claude/skills/successtree-integration/SKILL.md) | skill Claude Code : questionnaire de découverte, inspection du projet, génération du payload depuis un brief visuel ([gabarit](.claude/skills/successtree-integration/brief-template.md), [exemples](.claude/skills/successtree-integration/examples.md)) |
| [`examples/native-php/`](examples/native-php/README.md) | intégration type dans un projet PHP natif (singleton `forceRequest`, contrôleur, vue, seed) |
| [`examples/demo/`](examples/demo/index.html) | démo statique « cœur de l'entreprise » (Jouer / Construire) |

## Feuille de route v2

- Layouts supplémentaires : grille hexagonale (sphérier), colonnes (politiques), spirale, import SVG de silhouette.
- Historique et annulation dans l'éditeur ; édition collaborative avec verrou optimiste.
- Progression d'équipe (sujets multiples agrégés) et classements.
- Conditions temporelles (série de jours, date limite) et conditions composées imbriquées (`and`/`or`/`not`).
- Récompenses qui se déclenchent côté hôte via une file d'événements (webhooks).
- Mode lecture seule partageable (image / lien public) et export PNG/SVG.
- Accessibilité : navigation clavier complète entre nœuds, annonces lecteur d'écran, mode contraste élevé.
- Adaptateurs supplémentaires (PostgreSQL) et migrations de schéma versionnées.

## Licence

Please note that my chosen license requires anyone who modifies my code and offers it as a network-based service (SaaS) to publish their entire source code. Using this code in proprietary software is prohibited, and any modifications must be released under the same license.
