<?php

declare(strict_types=1);

use SuccessTree\Db\ForceRequestAdapter;
use SuccessTree\Http\Request;
use SuccessTree\SuccessTree;

/**
 * Contrôleur HÔTE : le projet garde son routing et son auth,
 * SuccessTree ne reçoit que la requête et un adaptateur BDD.
 */
final class SuccessTreeController
{
    /** @var array<string, mixed> */
    private $config;

    /** @param array<string, mixed> $config contenu de config.php */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /** Route /arbre/api : délégation totale à SuccessTree (JSON ou fichier d'asset). */
    public function api(): void
    {
        $this->successTree($this->currentUser())
            ->handle(Request::fromGlobals())
            ->send();
    }

    /** Route /arbre : page HTML de l'hôte avec le point de montage. */
    public function page(): void
    {
        $user  = $this->currentUser();
        $st    = $this->successTree($user);
        $title = 'Mon arbre de progression';
        $mount = $st->renderMount($this->config['successtree']['tree'], [
            'height' => '80vh',
            'edit'   => (bool)$user['is_admin'],
        ]);
        require __DIR__ . '/../views/successtree.php';
    }

    /**
     * Exemple d'appel depuis le code MÉTIER de l'hôte (ex. après publication d'un article) :
     *   (new SuccessTreeController($config))->track($userId, 'posts_published', 1);
     * Instance serveur de confiance : can_progress = true, indépendamment des droits du client.
     *
     * @return array<string, mixed> réponse {ok, data|error}
     */
    public function track(string $userId, string $metric, float $value, string $mode = 'inc'): array
    {
        $st = $this->successTree(['id' => $userId, 'is_admin' => false, 'has_paid' => false], true);
        return $st->handleArray(
            'metric',
            ['tree' => $this->config['successtree']['tree']],
            ['metric' => $metric, 'value' => $value, 'mode' => $mode]
        );
    }

    /** @param array<string, mixed> $user */
    private function successTree(array $user, bool $trusted = false): SuccessTree
    {
        $db = Database::getInstance();
        $adapter = new ForceRequestAdapter($db, [
            'method' => 'forceRequest',
            // Échappement relié à la connexion réelle (charset utf8mb4 pris en compte).
            'escape' => [$db->getConnection(), 'real_escape_string'],
            'driver' => 'mysql',
        ]);

        return new SuccessTree($adapter, [
            'prefix'       => $this->config['successtree']['prefix'],
            'subject'      => (string)$user['id'],
            'can_edit'     => (bool)$user['is_admin'],
            'can_progress' => $trusted,  // le navigateur ne se valide pas lui-même
            'endpoint'     => $this->config['successtree']['api'],
            'driver'       => 'mysql',
            'conditions'   => [
                'has_paid' => static function ($subject, $node, $params) use ($user): bool {
                    return !empty($user['has_paid']);
                },
            ],
            'on_complete'  => static function ($subject, $node, $tree): void {
                // Hook hôte : attribuer de l'XP, notifier, journaliser…
                $name = is_array($node) ? ($node['name'] ?? '?') : '?';
                error_log('[SuccessTree] sujet ' . $subject . ' a complété « ' . $name . ' »');
            },
        ]);
    }

    /** @return array<string, mixed> utilisateur courant (simulé ici, session réelle dans votre projet) */
    private function currentUser(): array
    {
        if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
            session_start();
        }
        if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
            return $_SESSION['user'];
        }
        return $this->config['demo_user'];
    }
}
