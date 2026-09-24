<?php

declare(strict_types=1);

namespace SuccessTree\Http;

use SuccessTree\Repository\ProgressRepository;
use SuccessTree\Repository\TreeRepository;
use SuccessTree\Schema\Installer;
use SuccessTree\Service\ProgressEngine;
use SuccessTree\Service\TreeValidator;
use SuccessTree\Service\ValidationException;
use SuccessTree\Support\Json;

/**
 * Dispatches the API actions of SPEC §6. Returns {"ok":true,"data":…} / {"ok":false,"error":"…"}.
 */
class ApiController
{
    /** action => [HTTP method, permission|null] */
    const ACTIONS = [
        'tree' => ['GET', null],
        'list' => ['GET', null],
        'complete' => ['POST', 'progress'],
        'uncomplete' => ['POST', 'edit'],
        'metric' => ['POST', 'progress'],
        'evaluate' => ['POST', null],
        'save' => ['POST', 'edit'],
        'delete' => ['POST', 'edit'],
        'install' => ['POST', 'edit'],
        'asset' => ['GET', null],
    ];

    const ASSETS = [
        'successtree.js' => 'application/javascript; charset=utf-8',
        'successtree.css' => 'text/css; charset=utf-8',
    ];

    /** @var TreeRepository */
    private $trees;

    /** @var ProgressRepository */
    private $progress;

    /** @var ProgressEngine */
    private $engine;

    /** @var TreeValidator */
    private $validator;

    /** @var Installer */
    private $installer;

    /** @var array */
    private $config;

    public function __construct(
        TreeRepository $trees,
        ProgressRepository $progress,
        ProgressEngine $engine,
        TreeValidator $validator,
        Installer $installer,
        array $config = []
    ) {
        $this->trees = $trees;
        $this->progress = $progress;
        $this->engine = $engine;
        $this->validator = $validator;
        $this->installer = $installer;
        $this->config = $config + [
            'subject' => null,
            'can_edit' => false,
            'can_progress' => false,
            'debug' => false,
            'assets_dir' => dirname(__DIR__, 2) . '/assets',
        ];
    }

    public function handle(Request $request): Response
    {
        if ($request->hasInvalidBody()) {
            return Response::json(['ok' => false, 'error' => 'Invalid JSON body'], 400);
        }
        $result = $this->execute($request->action(), $request->params(), $request->body(), $request->method());
        if ($result['status'] === 200 && $request->action() === 'asset') {
            $d = $result['payload']['data'];
            return Response::file($d['path'], $d['content_type'], $request->header('If-None-Match'));
        }
        return Response::json($result['payload'], $result['status']);
    }

    /**
     * Runs an action. $method = null skips the HTTP method check (host-managed output).
     *
     * @param mixed $body
     * @return array{status: int, payload: array}
     */
    public function execute(string $action, array $params = [], $body = null, ?string $method = null): array
    {
        try {
            if (!isset(self::ACTIONS[$action])) {
                throw new ApiException('Unknown action', 404);
            }
            list($verb, $perm) = self::ACTIONS[$action];
            if ($method !== null) {
                $m = strtoupper($method);
                $ok = $verb === 'GET' ? ($m === 'GET' || $m === 'HEAD') : $m === 'POST';
                if (!$ok) {
                    throw new ApiException('Method not allowed (expected ' . $verb . ')', 405);
                }
            }
            if ($perm === 'edit' && !$this->canEdit()) {
                throw new ApiException('Forbidden', 403);
            }
            if ($perm === 'progress' && !$this->canProgress()) {
                throw new ApiException('Forbidden', 403);
            }
            if ($body !== null && !is_array($body)) {
                throw new ApiException('Body must be a JSON object', 400);
            }
            $body = is_array($body) ? $body : [];
            $data = $this->{'action' . ucfirst($action)}($params, $body);
            return ['status' => 200, 'payload' => ['ok' => true, 'data' => $data]];
        } catch (ApiException $e) {
            return $this->error($e->getMessage(), $e->status());
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\DomainException $e) {
            $messages = [
                'node_locked' => 'Node is locked',
                'conditions_not_met' => 'Completion conditions are not met',
                'unknown_node' => 'Unknown node',
            ];
            $msg = isset($messages[$e->getMessage()]) ? $messages[$e->getMessage()] : 'Operation refused';
            return $this->error($msg, $e->getMessage() === 'unknown_node' ? 404 : 400);
        } catch (\Throwable $e) {
            $msg = !empty($this->config['debug']) ? get_class($e) . ': ' . $e->getMessage() : 'Internal error';
            return $this->error($msg, 500);
        }
    }

    private function error(string $message, int $status): array
    {
        return ['status' => $status, 'payload' => ['ok' => false, 'error' => $message]];
    }

    private function canEdit(): bool
    {
        return (bool)$this->config['can_edit'];
    }

    private function canProgress(): bool
    {
        return (bool)$this->config['can_progress'] || $this->canEdit();
    }

    // ----------------------------------------------------------------- actions

    private function actionTree(array $params, array $body): array
    {
        $tree = $this->requireTree($params);
        return $this->view($tree, $this->subject($params, $body));
    }

    private function actionList(array $params, array $body): array
    {
        return $this->trees->listTrees();
    }

    private function actionComplete(array $params, array $body): array
    {
        $tree = $this->requireTree($params);
        $subject = $this->requireSubject($params, $body);
        $node = $this->requireNodeUid($body);
        $data = null;
        if (isset($body['data'])) {
            if (!is_array($body['data'])) {
                throw new ApiException('data must be an object', 400);
            }
            $enc = json_encode($body['data']);
            if ($enc === false || strlen($enc) > 16384) {
                throw new ApiException('data is too large', 400);
            }
            $data = $body['data'];
        }
        $graph = $this->trees->loadGraph($tree['uid']);
        $this->assertNodeExists($graph, $node);
        $this->engine->complete(
            $tree,
            $graph,
            $subject,
            $node,
            $this->progress->completed($tree['uid'], $subject),
            $this->progress->metrics($subject),
            $data
        );
        return $this->view($tree, $subject, $graph);
    }

    private function actionUncomplete(array $params, array $body): array
    {
        $tree = $this->requireTree($params);
        $subject = $this->requireSubject($params, $body);
        $node = $this->requireNodeUid($body);
        $graph = $this->trees->loadGraph($tree['uid']);
        $this->assertNodeExists($graph, $node);
        $this->progress->uncomplete($tree['uid'], $node, $subject);
        return $this->view($tree, $subject, $graph);
    }

    private function actionMetric(array $params, array $body): array
    {
        $subject = $this->requireSubject($params, $body);
        $metric = isset($body['metric']) ? $body['metric'] : null;
        if (!is_string($metric) || !preg_match(TreeValidator::METRIC_RE, $metric)) {
            throw new ApiException('Invalid metric name', 400);
        }
        $value = isset($body['value']) ? $body['value'] : null;
        if (!(is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) || !is_finite((float)$value)) {
            throw new ApiException('value must be a number', 400);
        }
        $mode = isset($body['mode']) ? $body['mode'] : 'set';
        if ($mode !== 'set' && $mode !== 'inc') {
            throw new ApiException('mode must be set|inc', 400);
        }
        $new = $this->progress->updateMetric($subject, $metric, (float)$value, $mode);

        $ref = $this->param($params, 'tree');
        if ($ref !== '') {
            $tree = $this->requireTree($params);
            return $this->evaluateAndView($tree, $subject);
        }
        // No tree given: evaluate every tree for this subject.
        foreach ($this->trees->listTrees() as $t) {
            $full = $this->trees->findByUid($t['uid']);
            if ($full !== null) {
                $this->evaluateTree($full, $subject);
            }
        }
        return ['metric' => $metric, 'value' => self::num($new)];
    }

    private function actionEvaluate(array $params, array $body): array
    {
        $tree = $this->requireTree($params);
        $subject = $this->subject($params, $body);
        if ($subject === '') {
            return $this->view($tree, '');
        }
        return $this->evaluateAndView($tree, $subject);
    }

    private function actionSave(array $params, array $body): array
    {
        if (isset($body['tree']) && is_array($body['tree'])) {
            $ref = $this->param($params, 'tree');
            if ((!isset($body['tree']['slug']) || $body['tree']['slug'] === '') && $ref !== '') {
                $body['tree']['slug'] = $ref;
            }
        }
        $payload = $this->validator->validate($body);

        $existing = null;
        if ($payload['tree']['uid'] !== null) {
            $existing = $this->trees->findByUid($payload['tree']['uid']);
        }
        if ($existing === null) {
            $ref = $this->param($params, 'tree');
            $existing = $this->trees->find($ref !== '' ? $ref : $payload['tree']['slug']);
            if ($existing !== null && $payload['tree']['uid'] !== null && $existing['uid'] !== $payload['tree']['uid']) {
                throw new ApiException('tree.uid does not match the existing tree', 400);
            }
        }
        $uid = $this->trees->save($payload, $existing);
        $tree = $this->trees->findByUid($uid);
        if ($tree === null) {
            throw new \RuntimeException('Tree not found after save');
        }
        return $this->view($tree, $this->subject($params, $body));
    }

    private function actionDelete(array $params, array $body): array
    {
        $tree = $this->requireTree($params);
        $this->trees->delete($tree['uid']);
        return ['deleted' => $tree['slug'], 'uid' => $tree['uid']];
    }

    private function actionInstall(array $params, array $body): array
    {
        $this->installer->install();
        return ['installed' => true];
    }

    private function actionAsset(array $params, array $body): array
    {
        $file = $this->param($params, 'file');
        if (!isset(self::ASSETS[$file])) {
            throw new ApiException('Unknown asset', 404);
        }
        $path = rtrim((string)$this->config['assets_dir'], '/\\') . '/' . $file;
        if (!is_file($path)) {
            throw new ApiException('Asset not found', 404);
        }
        return ['file' => $file, 'path' => $path, 'content_type' => self::ASSETS[$file]];
    }

    // ----------------------------------------------------------------- helpers

    /** Evaluates auto-completions for a subject on a tree (used by the facade too). */
    public function evaluateTree(array $tree, string $subject, ?array $graph = null): array
    {
        $graph = $graph !== null ? $graph : $this->trees->loadGraph($tree['uid']);
        return $this->engine->evaluate(
            $tree,
            $graph,
            $subject,
            $this->progress->completed($tree['uid'], $subject),
            $this->progress->metrics($subject)
        );
    }

    private function evaluateAndView(array $tree, string $subject): array
    {
        $graph = $this->trees->loadGraph($tree['uid']);
        $this->evaluateTree($tree, $subject, $graph);
        return $this->view($tree, $subject, $graph);
    }

    /**
     * Builds the SPEC §4 document for a tree and a subject.
     */
    public function view(array $tree, string $subject, ?array $graph = null): array
    {
        $graph = $graph !== null ? $graph : $this->trees->loadGraph($tree['uid']);
        $completed = $subject !== '' ? $this->progress->completed($tree['uid'], $subject) : [];
        $metrics = $subject !== '' ? $this->progress->metrics($subject) : [];
        $statuses = $this->engine->statuses($graph, $completed, $metrics, $subject);

        $nodes = [];
        foreach ($graph['nodes'] as $n) {
            $conds = [];
            foreach ($n['conditions'] as $c) {
                $conds[] = [
                    'uid' => $c['uid'],
                    'phase' => $c['phase'],
                    'type' => $c['type'],
                    'params' => Json::out($c['params']),
                ];
            }
            $progress = ['completed_at' => isset($completed[$n['uid']]) ? $completed[$n['uid']]['completed_at'] : null];
            if (isset($completed[$n['uid']]) && $completed[$n['uid']]['data']) {
                $progress['data'] = $completed[$n['uid']]['data'];
            }
            $nodes[] = [
                'uid' => $n['uid'],
                'parent' => $n['parent'],
                'kind' => $n['kind'],
                'slug' => $n['slug'],
                'name' => $n['name'],
                'description' => $n['description'],
                'icon' => $n['icon'],
                'x' => $n['x'] === null ? null : self::num($n['x']),
                'y' => $n['y'] === null ? null : self::num($n['y']),
                'theme' => Json::out($n['theme']),
                'reward' => Json::out($n['reward']),
                'sort' => $n['sort'],
                'meta' => Json::out($n['meta']),
                'conditions' => $conds,
                'status' => isset($statuses[$n['uid']]) ? $statuses[$n['uid']] : 'locked',
                'progress' => $progress,
            ];
        }
        $m = [];
        foreach ($metrics as $k => $v) {
            $m[$k] = self::num($v);
        }
        return [
            'tree' => [
                'uid' => $tree['uid'],
                'slug' => $tree['slug'],
                'name' => $tree['name'],
                'description' => $tree['description'],
                'layout' => $tree['layout'],
                'theme' => Json::out($tree['theme']),
                'settings' => Json::out($tree['settings']),
            ],
            'nodes' => $nodes,
            'links' => $graph['links'],
            'subject' => $subject,
            'metrics' => Json::out($m),
            'permissions' => ['edit' => $this->canEdit(), 'progress' => $this->canProgress()],
        ];
    }

    /** @return int|float */
    private static function num(float $v)
    {
        return (floor($v) === $v && abs($v) < 9.0e15) ? (int)$v : $v;
    }

    private function param(array $params, string $key): string
    {
        return isset($params[$key]) && is_string($params[$key]) ? $params[$key] : '';
    }

    private function requireTree(array $params): array
    {
        $ref = $this->param($params, 'tree');
        if ($ref === '' || strlen($ref) > 120) {
            throw new ApiException('Missing tree parameter', 400);
        }
        $tree = $this->trees->find($ref);
        if ($tree === null) {
            throw new ApiException('Tree not found', 404);
        }
        return $tree;
    }

    /** Current subject; editors may target another subject via ?subject= or body.subject. */
    private function subject(array $params, array $body): string
    {
        $s = $this->config['subject'];
        if ($this->canEdit()) {
            if (isset($body['subject']) && (is_string($body['subject']) || is_int($body['subject']))) {
                $s = (string)$body['subject'];
            } elseif (isset($params['subject']) && is_string($params['subject'])) {
                $s = $params['subject'];
            }
        }
        $s = $s === null ? '' : (string)$s;
        if (strlen($s) > 128) {
            throw new ApiException('Invalid subject', 400);
        }
        return $s;
    }

    private function requireSubject(array $params, array $body): string
    {
        $s = $this->subject($params, $body);
        if ($s === '') {
            throw new ApiException('No subject', 403);
        }
        return $s;
    }

    private function requireNodeUid(array $body): string
    {
        $node = isset($body['node']) ? $body['node'] : null;
        if (!TreeValidator::isUid($node)) {
            throw new ApiException('Invalid node uid', 400);
        }
        return $node;
    }

    private function assertNodeExists(array $graph, string $uid): void
    {
        foreach ($graph['nodes'] as $n) {
            if ($n['uid'] === $uid) {
                return;
            }
        }
        throw new ApiException('Node not found', 404);
    }
}
