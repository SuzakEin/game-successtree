# Exemple : intégration dans un projet PHP natif

Projet hôte minimal : singleton `Database` maison exposant `forceRequest($requete)`, mini front-controller,
un contrôleur hôte qui délègue à SuccessTree. Voir le protocole complet dans [`docs/INTEGRATION.md`](../../docs/INTEGRATION.md).

| Fichier | Rôle |
|---|---|
| `Database.php` | singleton mysqli de l'hôte (`forceRequest`, `getConnection`) — jamais modifié par SuccessTree |
| `config.php` | identifiants BDD (surchargeables par variables d'environnement), routes, slug de l'arbre |
| `controllers/SuccessTreeController.php` | `api()` délègue à `$st->handle(Request::fromGlobals())->send()`, `page()` rend la vue, `track()` montre l'appel depuis le code métier |
| `views/successtree.php` | layout HTML qui affiche `renderMount()` |
| `index.php` | routeur : `/arbre` (page) et `/arbre/api` (API + assets) |
| `seed.php` | crée les tables et enregistre l'arbre démo cœur (`Seed\HeartDemo`) |

## Lancer

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS successtree_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
cd examples/native-php
DB_USER=root DB_PASS= php seed.php
php -S localhost:8000 index.php
# → http://localhost:8000/arbre
```

Vérification rapide de l'API : `curl 'http://localhost:8000/arbre/api?action=tree&tree=coeur'` doit renvoyer `{"ok":true,"data":{…}}`.

L'utilisateur est simulé dans `config.php` (`demo_user`, admin par défaut) : passez `is_admin` à `false` pour voir
le mode joueur seul.
