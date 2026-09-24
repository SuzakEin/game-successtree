<?php

declare(strict_types=1);

namespace SuccessTree;

use SuccessTree\Db\DbAdapterInterface;
use SuccessTree\Http\ApiController;
use SuccessTree\Http\Request;
use SuccessTree\Http\Response;
use SuccessTree\Repository\ProgressRepository;
use SuccessTree\Repository\TreeRepository;
use SuccessTree\Schema\Installer;
use SuccessTree\Service\ProgressEngine;
use SuccessTree\Service\TreeValidator;

/**
 * SuccessTree façade. The host exposes ONE route and delegates to it:
 *
 *   $st = new SuccessTree\SuccessTree($adapter, ['subject' => (string)$userId, 'can_edit' => $isAdmin, 'endpoint' => '/ma-route']);
 *   $st->handle(SuccessTree\Http\Request::fromGlobals())->send();
 *   echo $st->renderMount('love', ['height' => '80vh', 'edit' => true]);
 */
class SuccessTree
{
    const VERSION = '1.0.0';

    /** @var DbAdapterInterface */
    private $db;

    /** @var array */
    private $config;

    /** @var TreeRepository */
    private $trees;

    /** @var ProgressRepository */
    private $progress;

    /** @var ProgressEngine */
    private $engine;

    /** @var Installer */
    private $installer;

    /** @var ApiController */
    private $controller;

    public function __construct(DbAdapterInterface $db, array $config = [])
    {
        $config += [
            'prefix' => 'st_',
            'subject' => null,
            'can_edit' => false,
            'can_progress' => false,
            'conditions' => [],
            'on_complete' => null,
            'endpoint' => '',
            'driver' => null,
            'debug' => false,
            'assets_dir' => dirname(__DIR__) . '/assets',
            'transactions' => true,
            'limits' => [],
        ];
        if (!is_string($config['prefix']) || !preg_match('/^[a-zA-Z0-9_]*$/', $config['prefix'])) {
            throw new \InvalidArgumentException('Invalid table prefix (allowed: [a-zA-Z0-9_])');
        }
        if ($config['subject'] !== null) {
            $config['subject'] = (string)$config['subject'];
        }
        if (!is_array($config['conditions'])) {
            $config['conditions'] = [];
        }
        $onComplete = is_callable($config['on_complete']) ? $config['on_complete'] : null;

        $this->db = $db;
        $this->config = $config;
        $this->trees = new TreeRepository($db, $config['prefix'], (bool)$config['transactions']);
        $this->progress = new ProgressRepository($db, $config['prefix']);
        $this->engine = new ProgressEngine($this->progress, $config['conditions'], $onComplete);
        $this->installer = new Installer($db, $config['prefix'], $config['driver']);
        $this->controller = new ApiController(
            $this->trees,
            $this->progress,
            $this->engine,
            new TreeValidator(is_array($config['limits']) ? $config['limits'] : []),
            $this->installer,
            $config
        );
    }

    /** Handles an HTTP request (method checked, permissions enforced). */
    public function handle(Request $request): Response
    {
        return $this->controller->handle($request);
    }

    /**
     * For hosts that manage the output themselves. The HTTP method is NOT checked here
     * (pass $method to enforce it). Returns {"ok":true,"data":…} or {"ok":false,"error":"…","status":4xx}.
     *
     * @param mixed $body decoded JSON body
     */
    public function handleArray(string $action, array $params = [], $body = null, ?string $method = null): array
    {
        $params['action'] = $action;
        $result = $this->controller->execute($action, $params, $body, $method);
        $payload = $result['payload'];
        if ($result['status'] !== 200) {
            $payload['status'] = $result['status'];
        }
        return $payload;
    }

    /** Creates the tables (CREATE TABLE IF NOT EXISTS). */
    public function install(): void
    {
        $this->installer->install();
    }

    /**
     * Server-side helpers (no permission check: the host calls them deliberately).
     */
    public function tree(string $slugOrUid, ?string $subject = null): ?array
    {
        $tree = $this->trees->find($slugOrUid);
        if ($tree === null) {
            return null;
        }
        return $this->controller->view($tree, $subject !== null ? $subject : (string)$this->config['subject']);
    }

    /**
     * Updates a metric for a subject then runs auto-completion on every tree. Returns the new value.
     */
    public function metric(string $metric, float $value, string $mode = 'set', ?string $subject = null): float
    {
        $subject = $subject !== null ? $subject : (string)$this->config['subject'];
        if ($subject === '' || !preg_match(TreeValidator::METRIC_RE, $metric)) {
            throw new \InvalidArgumentException('Invalid subject or metric');
        }
        $new = $this->progress->updateMetric($subject, $metric, $value, $mode === 'inc' ? 'inc' : 'set');
        $this->evaluateAll($subject);
        return $new;
    }

    /**
     * Runs auto-completion for a subject on one tree (or all trees when $slugOrUid is null).
     *
     * @return string[] newly completed node uids
     */
    public function evaluate(?string $slugOrUid = null, ?string $subject = null): array
    {
        $subject = $subject !== null ? $subject : (string)$this->config['subject'];
        if ($subject === '') {
            return [];
        }
        if ($slugOrUid === null) {
            return $this->evaluateAll($subject);
        }
        $tree = $this->trees->find($slugOrUid);
        return $tree === null ? [] : $this->controller->evaluateTree($tree, $subject);
    }

    /**
     * Marks a node completed for a subject (host/admin validation, e.g. "manual" nodes).
     * Throws \DomainException (node_locked | conditions_not_met | unknown_node).
     *
     * @return string[] newly completed uids (cascade included)
     */
    public function complete(string $slugOrUid, string $nodeUid, ?string $subject = null, ?array $data = null): array
    {
        $subject = $subject !== null ? $subject : (string)$this->config['subject'];
        $tree = $this->trees->find($slugOrUid);
        if ($tree === null || $subject === '') {
            throw new \DomainException('unknown_node');
        }
        $graph = $this->trees->loadGraph($tree['uid']);
        return $this->engine->complete(
            $tree,
            $graph,
            $subject,
            $nodeUid,
            $this->progress->completed($tree['uid'], $subject),
            $this->progress->metrics($subject),
            $data
        );
    }

    /** @return string[] */
    private function evaluateAll(string $subject): array
    {
        $all = [];
        foreach ($this->trees->listTrees() as $t) {
            $tree = $this->trees->findByUid($t['uid']);
            if ($tree !== null) {
                $all = array_merge($all, $this->controller->evaluateTree($tree, $subject));
            }
        }
        return $all;
    }

    /**
     * HTML mount point: <link> + <div data-successtree …> + <script>.
     * Options: height, edit (bool, honoured only if can_edit), assets_url, assets (bool, default true),
     *          class, id, endpoint.
     */
    public function renderMount(string $treeSlug, array $opts = []): string
    {
        $endpoint = isset($opts['endpoint']) ? (string)$opts['endpoint'] : (string)$this->config['endpoint'];
        $edit = !empty($opts['edit']) && !empty($this->config['can_edit']);
        $canProgress = !empty($this->config['can_progress']) || !empty($this->config['can_edit']);
        $h = static function ($s) {
            return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        if (!empty($opts['assets_url'])) {
            $base = rtrim((string)$opts['assets_url'], '/');
            $css = $base . '/successtree.css';
            $js = $base . '/successtree.js';
        } else {
            $sep = strpos($endpoint, '?') === false ? '?' : '&';
            $css = $endpoint . $sep . 'action=asset&file=successtree.css&v=' . self::VERSION;
            $js = $endpoint . $sep . 'action=asset&file=successtree.js&v=' . self::VERSION;
        }

        $style = '';
        if (isset($opts['height']) && preg_match('/^\d+(\.\d+)?(px|vh|vw|%|em|rem)$/', (string)$opts['height'])) {
            $style = ' style="height:' . $h($opts['height']) . '"';
        }
        $attrs = ' data-successtree'
            . ' data-endpoint="' . $h($endpoint) . '"'
            . ' data-tree="' . $h($treeSlug) . '"'
            . ' data-edit="' . ($edit ? '1' : '0') . '"'
            . ' data-can-progress="' . ($canProgress ? '1' : '0') . '"';
        if (!empty($opts['id'])) {
            $attrs = ' id="' . $h($opts['id']) . '"' . $attrs;
        }
        $class = 'successtree' . (!empty($opts['class']) ? ' ' . $opts['class'] : '');

        $html = '';
        $withAssets = !array_key_exists('assets', $opts) || $opts['assets'];
        if ($withAssets) {
            $html .= '<link rel="stylesheet" href="' . $h($css) . '">' . "\n";
        }
        $html .= '<div class="' . $h($class) . '"' . $attrs . $style . '></div>' . "\n";
        if ($withAssets) {
            $html .= '<script src="' . $h($js) . '" defer></script>' . "\n";
        }
        return $html;
    }

    public function config(): array
    {
        return $this->config;
    }

    public function trees(): TreeRepository
    {
        return $this->trees;
    }

    public function progress(): ProgressRepository
    {
        return $this->progress;
    }

    public function engine(): ProgressEngine
    {
        return $this->engine;
    }

    public function db(): DbAdapterInterface
    {
        return $this->db;
    }
}
