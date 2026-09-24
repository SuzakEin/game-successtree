<?php

/**
 * SuccessTree — framework-free test runner.  Usage: php tests/run.php
 * Exit code 0 when every test passes, 1 otherwise.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
set_error_handler(static function ($no, $str, $file, $line) {
    throw new ErrorException($str, 0, $no, $file, $line);
});

require __DIR__ . '/../src/autoload.php';

use SuccessTree\Db\AbstractAdapter;
use SuccessTree\Db\ForceRequestAdapter;
use SuccessTree\Db\PdoAdapter;
use SuccessTree\Http\Request;
use SuccessTree\Seed\HeartDemo;
use SuccessTree\Service\Layout;
use SuccessTree\Service\ProgressEngine;
use SuccessTree\Service\TreeValidator;
use SuccessTree\Service\ValidationException;
use SuccessTree\SuccessTree;

// ---------------------------------------------------------------- mini framework

$GLOBALS['__tests'] = [];
$GLOBALS['__assertions'] = 0;

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = [$name, $fn];
}

final class AssertionFailed extends Exception
{
}

function ok($cond, string $msg = 'assertion failed'): void
{
    $GLOBALS['__assertions']++;
    if (!$cond) {
        throw new AssertionFailed($msg);
    }
}

function eq($expected, $actual, string $msg = ''): void
{
    $GLOBALS['__assertions']++;
    if ($expected !== $actual) {
        throw new AssertionFailed(($msg !== '' ? $msg . ': ' : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function throws(callable $fn, string $class, string $msg = ''): Throwable
{
    $GLOBALS['__assertions']++;
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return $e;
        }
        throw new AssertionFailed(($msg ?: 'wrong exception') . ': expected ' . $class . ', got ' . get_class($e) . ' (' . $e->getMessage() . ')');
    }
    throw new AssertionFailed(($msg ?: 'no exception') . ': expected ' . $class);
}

// ---------------------------------------------------------------- fixtures

/** Fake host singleton exposing forceRequest(), backed by PDO sqlite. */
class FakeDatabase
{
    /** @var FakeDatabase|null */
    private static $instance;

    /** @var PDO public so that the adapter can detect it and use ->quote() */
    public $pdo;

    /** @var string array|statement|objects */
    public $mode = 'array';

    /** @var string[] */
    public $log = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function fresh(): self
    {
        self::$instance = new self();
        return self::$instance;
    }

    private function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /** @return mixed */
    public function forceRequest(string $sql)
    {
        $this->log[] = $sql;
        $stmt = $this->pdo->query($sql);
        if ($stmt->columnCount() === 0) {
            return true; // write: bool
        }
        if ($this->mode === 'statement') {
            return $stmt;
        }
        if ($this->mode === 'objects') {
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/** Host with a private connection and a renamed method: forces the manual escaping fallback. */
class PrivateHost
{
    /** @var PDO */
    private $conn;

    public function __construct()
    {
        $this->conn = new PDO('sqlite::memory:');
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /** @return mixed */
    public function query(string $sql)
    {
        $stmt = $this->conn->query($sql);
        return $stmt->columnCount() === 0 ? 1 : $stmt;
    }
}

function sqliteAdapter(): PdoAdapter
{
    return new PdoAdapter(new PDO('sqlite::memory:'));
}

function makeSt(array $config = [], $adapter = null): SuccessTree
{
    $adapter = $adapter ?: sqliteAdapter();
    $st = new SuccessTree($adapter, $config + ['subject' => 'u1', 'can_edit' => true, 'can_progress' => true, 'debug' => true]);
    $st->install();
    return $st;
}

function smallPayload(): array
{
    return [
        'tree' => ['slug' => 'love', 'name' => 'Amour', 'description' => 'Test', 'layout' => 'heart',
            'theme' => ['background' => '#2b2d5c'], 'settings' => ['expandPush' => 1.6]],
        'nodes' => [
            ['uid' => 'o', 'parent' => null, 'kind' => 'origin', 'name' => 'Départ', 'icon' => 'target', 'x' => 0, 'y' => 0,
                'theme' => ['color' => '#ff5c8a'], 'reward' => ['xp' => 10], 'sort' => 0, 'meta' => [], 'conditions' => []],
            ['uid' => 'h1', 'parent' => 'o', 'kind' => 'hub', 'name' => 'Hub 1', 'x' => 10.5, 'y' => -20, 'sort' => 1],
            ['uid' => 'n1', 'parent' => 'h1', 'kind' => 'node', 'name' => 'Nœud 1', 'x' => null, 'y' => null, 'sort' => 2,
                'conditions' => [['uid' => 'c1', 'phase' => 'complete', 'type' => 'metric', 'params' => ['metric' => 'posts', 'op' => '>=', 'value' => 3]]]],
            ['uid' => 'n2', 'parent' => 'n1', 'kind' => 'node', 'name' => 'Nœud 2', 'sort' => 3,
                'conditions' => [['phase' => 'complete', 'type' => 'node', 'params' => ['node' => 'n1']]]],
            ['uid' => 'n3', 'parent' => 'h1', 'kind' => 'node', 'name' => 'Manuel', 'sort' => 4,
                'conditions' => [['uid' => 'c3', 'phase' => 'complete', 'type' => 'manual', 'params' => []]]],
        ],
        'links' => [['uid' => 'l1', 'from' => 'n2', 'to' => 'n3', 'type' => 'visual']],
    ];
}

function nodeBy(array $data, string $uid): array
{
    foreach ($data['nodes'] as $n) {
        if ($n['uid'] === $uid) {
            return $n;
        }
    }
    throw new AssertionFailed('node ' . $uid . ' not found');
}

function okData(array $res): array
{
    if (empty($res['ok'])) {
        throw new AssertionFailed('API error: ' . (isset($res['error']) ? $res['error'] : '?') . ' (status ' . (isset($res['status']) ? $res['status'] : '?') . ')');
    }
    return $res['data'];
}

/** Builds an in-memory graph for the engine. */
function g(array $nodes, array $links = []): array
{
    $out = [];
    foreach ($nodes as $n) {
        $out[] = $n + ['parent' => null, 'kind' => 'node', 'conditions' => []];
    }
    return ['nodes' => $out, 'links' => $links];
}

// ---------------------------------------------------------------- tests

test('install creates every table and is idempotent', function () {
    $pdo = new PDO('sqlite::memory:');
    $st = new SuccessTree(new PdoAdapter($pdo), ['prefix' => 'gx_']);
    $st->install();
    $st->install();
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    eq(['gx_condition', 'gx_link', 'gx_metric', 'gx_node', 'gx_progress', 'gx_tree'], $tables);
    throws(function () use ($pdo) {
        new SuccessTree(new PdoAdapter($pdo), ['prefix' => 'st`; DROP']);
    }, InvalidArgumentException::class);
    ok(strpos(file_get_contents(__DIR__ . '/../schema/mysql.sql'), 'ENGINE=InnoDB') !== false, 'mysql.sql present');
});

test('save + tree roundtrip (format §4)', function () {
    $st = makeSt();
    $data = okData($st->handleArray('save', [], smallPayload(), 'POST'));
    eq('love', $data['tree']['slug']);
    ok(preg_match('/^t_[0-9a-f]{12}$/', $data['tree']['uid']) === 1, 'generated tree uid');

    $data = okData($st->handleArray('tree', ['tree' => 'love'], null, 'GET'));
    eq(['uid', 'slug', 'name', 'description', 'layout', 'theme', 'settings'], array_keys($data['tree']));
    eq(['tree', 'nodes', 'links', 'subject', 'metrics', 'permissions'], array_keys($data));
    eq(5, count($data['nodes']));
    $o = nodeBy($data, 'o');
    eq(['uid', 'parent', 'kind', 'slug', 'name', 'description', 'icon', 'x', 'y', 'theme', 'reward', 'sort', 'meta', 'conditions', 'status', 'progress'], array_keys($o));
    eq('available', $o['status']);
    eq(['completed_at' => null], $o['progress']);
    eq(0, $o['x']);
    $h1 = nodeBy($data, 'h1');
    eq(10.5, $h1['x']);
    eq(-20, $h1['y']);
    eq('locked', $h1['status']);
    $n1 = nodeBy($data, 'n1');
    eq(null, $n1['x']);
    eq('c1', $n1['conditions'][0]['uid']);
    eq(['metric' => 'posts', 'op' => '>=', 'value' => 3], $n1['conditions'][0]['params']);
    $n2 = nodeBy($data, 'n2');
    ok(preg_match('/^c_[0-9a-f]{12}$/', $n2['conditions'][0]['uid']) === 1, 'generated condition uid');
    eq([['uid' => 'l1', 'from' => 'n2', 'to' => 'n3', 'type' => 'visual']], $data['links']);
    eq('u1', $data['subject']);
    eq(['edit' => true, 'progress' => true], $data['permissions']);

    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    ok(strpos($json, '"meta":{}') !== false, 'empty meta encoded as {}');
    ok(strpos($json, '"metrics":{}') !== false, 'empty metrics encoded as {}');
    ok(strpos($json, '"Nœud 1"') !== false, 'unicode kept');

    $list = okData($st->handleArray('list', [], null, 'GET'));
    eq([['uid' => $data['tree']['uid'], 'slug' => 'love', 'name' => 'Amour', 'description' => 'Test', 'layout' => 'heart']], $list);
});

test('save keeps progress of surviving nodes, drops the others', function () {
    $st = makeSt();
    okData($st->handleArray('save', [], smallPayload()));
    okData($st->handleArray('complete', ['tree' => 'love'], ['node' => 'o']));
    okData($st->handleArray('complete', ['tree' => 'love'], ['node' => 'h1']));
    okData($st->handleArray('complete', ['tree' => 'love'], ['node' => 'n3']));
    $p = smallPayload();
    $p['tree']['name'] = 'Renamed';
    $p['tree']['slug'] = 'love2';
    array_pop($p['nodes']);            // drop n3
    $p['links'] = [];
    $data = okData($st->handleArray('save', ['tree' => 'love'], $p));
    eq('love2', $data['tree']['slug']);
    eq('Renamed', $data['tree']['name']);
    eq('completed', nodeBy($data, 'o')['status']);
    eq('completed', nodeBy($data, 'h1')['status']);
    eq(4, count($data['nodes']));
    eq([], $data['links']);
    $rows = $st->db()->select("SELECT node_uid FROM `st_progress` ORDER BY node_uid");
    eq(['h1', 'o'], array_column($rows, 'node_uid'));
    eq(1, count($st->db()->select('SELECT uid FROM `st_tree`')), 'no duplicate tree');
});

test('validator rejects invalid payloads', function () {
    $v = new TreeValidator();
    $cases = [
        'no origin' => function ($p) { $p['nodes'][0]['kind'] = 'hub'; return $p; },
        'two origins' => function ($p) { $p['nodes'][1]['kind'] = 'origin'; $p['nodes'][1]['parent'] = null; return $p; },
        'bad uid' => function ($p) { $p['nodes'][1]['uid'] = "h1'; DROP"; return $p; },
        'dup uid' => function ($p) { $p['nodes'][2]['uid'] = 'h1'; return $p; },
        'bad kind' => function ($p) { $p['nodes'][1]['kind'] = 'boss'; return $p; },
        'unknown parent' => function ($p) { $p['nodes'][2]['parent'] = 'zzz'; return $p; },
        'parent cycle' => function ($p) { $p['nodes'][1]['parent'] = 'n2'; return $p; },
        'unknown condition' => function ($p) { $p['nodes'][2]['conditions'][0]['type'] = 'eval'; return $p; },
        'bad phase' => function ($p) { $p['nodes'][2]['conditions'][0]['phase'] = 'later'; return $p; },
        'metric op' => function ($p) { $p['nodes'][2]['conditions'][0]['params']['op'] = '=>'; return $p; },
        'metric value' => function ($p) { $p['nodes'][2]['conditions'][0]['params']['value'] = 'ten'; return $p; },
        'node cond target' => function ($p) { $p['nodes'][3]['conditions'][0]['params']['node'] = 'ghost'; return $p; },
        'link target' => function ($p) { $p['links'][0]['to'] = 'ghost'; return $p; },
        'link type' => function ($p) { $p['links'][0]['type'] = 'teleport'; return $p; },
        'theme string' => function ($p) { $p['nodes'][0]['theme'] = 'red'; return $p; },
        'meta scalar' => function ($p) { $p['nodes'][0]['meta'] = 42; return $p; },
        'long name' => function ($p) { $p['nodes'][0]['name'] = str_repeat('é', 191); return $p; },
        'bad slug' => function ($p) { $p['tree']['slug'] = 'a b'; return $p; },
        'bad layout' => function ($p) { $p['tree']['layout'] = 'spiral'; return $p; },
        'x not number' => function ($p) { $p['nodes'][1]['x'] = 'left'; return $p; },
        'nodes not list' => function ($p) { $p['nodes'] = ['a' => $p['nodes'][0]]; return $p; },
        'origin with parent' => function ($p) { $p['nodes'][0]['parent'] = 'h1'; return $p; },
    ];
    foreach ($cases as $label => $mutate) {
        throws(function () use ($v, $mutate) {
            $v->validate($mutate(smallPayload()));
        }, ValidationException::class, $label);
    }
    $big = smallPayload();
    for ($i = 0; $i < 60; $i++) {
        $big['nodes'][] = ['uid' => 'x' . $i, 'parent' => 'o', 'kind' => 'node', 'name' => 'x'];
    }
    $small = new TreeValidator(['nodes' => 50]);
    throws(function () use ($small, $big) {
        $small->validate($big);
    }, ValidationException::class, 'max nodes');
    $ok = $v->validate(smallPayload());
    eq(5, count($ok['nodes']));

    // Through the API: 400 + JSON error
    $st = makeSt();
    $p = smallPayload();
    $p['nodes'][0]['kind'] = 'hub';
    $res = $st->handleArray('save', [], $p, 'POST');
    eq(false, $res['ok']);
    eq(400, $res['status']);
    ok(strpos($res['error'], 'origin') !== false, 'error mentions origin');
});

test('conditions: every type', function () {
    $cb = ['has_paid' => function ($subject, $node, $params) { return $subject === 'vip'; }];
    $e = new ProgressEngine(null, $cb);
    $graph = g([
        ['uid' => 'o', 'kind' => 'origin'],
        ['uid' => 'a', 'parent' => 'o'],
        ['uid' => 'b', 'parent' => 'o'],
        ['uid' => 'c', 'parent' => 'o'],
        ['uid' => 'hub', 'kind' => 'hub'],                           // no parent: depends on origin
        ['uid' => 'p_node', 'conditions' => [['phase' => 'unlock', 'type' => 'node', 'params' => ['node' => 'a']]]],
        ['uid' => 'p_all', 'conditions' => [['phase' => 'unlock', 'type' => 'all', 'params' => ['nodes' => ['a', 'b']]]]],
        ['uid' => 'p_any', 'conditions' => [['phase' => 'unlock', 'type' => 'any', 'params' => ['nodes' => ['a', 'b', 'c'], 'min' => 2]]]],
        ['uid' => 'p_children', 'parent' => 'o', 'conditions' => [['phase' => 'unlock', 'type' => 'children', 'params' => ['min' => null]]]],
        ['uid' => 'k1', 'parent' => 'p_children', 'conditions' => [['phase' => 'unlock', 'type' => 'node', 'params' => ['node' => 'o']]]],
        ['uid' => 'k2', 'parent' => 'p_children', 'conditions' => [['phase' => 'unlock', 'type' => 'node', 'params' => ['node' => 'o']]]],
        ['uid' => 'p_min', 'conditions' => [['phase' => 'unlock', 'type' => 'children', 'params' => ['min' => 1]]]],
        ['uid' => 'm1', 'parent' => 'p_min'],
        ['uid' => 'p_metric', 'conditions' => [['phase' => 'unlock', 'type' => 'metric', 'params' => ['metric' => 'xp', 'op' => '>=', 'value' => 10]]]],
        ['uid' => 'p_metric_ne', 'conditions' => [['phase' => 'unlock', 'type' => 'metric', 'params' => ['metric' => 'xp', 'op' => '!=', 'value' => 10]]]],
        ['uid' => 'p_cb', 'conditions' => [['phase' => 'unlock', 'type' => 'callback', 'params' => ['name' => 'has_paid']]]],
        ['uid' => 'p_cb_missing', 'conditions' => [['phase' => 'unlock', 'type' => 'callback', 'params' => ['name' => 'nope']]]],
        ['uid' => 'p_parent', 'parent' => 'a', 'conditions' => [['phase' => 'unlock', 'type' => 'parent', 'params' => []]]],
        ['uid' => 'multi', 'parent' => 'a'],                           // + path link from b
        ['uid' => 'vis', 'parent' => 'a'],                             // + visual link from b (ignored)
        ['uid' => 'man', 'parent' => 'o', 'conditions' => [['phase' => 'complete', 'type' => 'manual', 'params' => []]]],
    ], [
        ['uid' => 'l1', 'from' => 'b', 'to' => 'multi', 'type' => 'path'],
        ['uid' => 'l2', 'from' => 'b', 'to' => 'vis', 'type' => 'visual'],
    ]);

    $s = $e->statuses($graph, [], [], 'u');
    eq('available', $s['o']);
    eq('locked', $s['a']);
    eq('locked', $s['hub']);
    $s = $e->statuses($graph, ['o' => 1], [], 'u');
    eq('available', $s['a']);
    eq('available', $s['hub'], 'hub without parent depends on origin');
    eq('locked', $s['p_node']);
    eq('locked', $s['p_children']);

    $done = ['o' => 1, 'a' => 1];
    $s = $e->statuses($graph, $done, ['xp' => 10], 'u');
    eq('completed', $s['a']);
    eq('available', $s['p_node']);
    eq('locked', $s['p_all']);
    eq('locked', $s['p_any'], 'any min 2 with 1');
    eq('available', $s['p_parent']);
    eq('locked', $s['multi'], 'path link = extra prerequisite');
    eq('available', $s['vis'], 'visual link ignored');
    eq('available', $s['p_metric']);
    eq('locked', $s['p_metric_ne']);
    eq('locked', $s['p_cb']);
    eq('locked', $s['p_cb_missing']);
    eq('locked', $s['p_min']);

    $done = ['o' => 1, 'a' => 1, 'b' => 1, 'k1' => 1, 'm1' => 1];
    $s = $e->statuses($graph, $done, ['xp' => 3], 'vip');
    eq('available', $s['p_all']);
    eq('available', $s['p_any']);
    eq('available', $s['multi']);
    eq('locked', $s['p_children'], 'children: all required');
    eq('available', $s['p_min'], 'children min 1');
    eq('locked', $s['p_metric']);
    eq('available', $s['p_metric_ne']);
    eq('available', $s['p_cb']);
    $done['k2'] = 1;
    eq('available', $e->statuses($graph, $done, [], 'u')['p_children']);

    // manual: never auto, allowed explicitly
    eq([], $e->autoCompletions($graph, ['o' => 1], [], 'u'));
    eq(null, $e->completionError($graph, 'man', ['o' => 1], [], 'u'));
    eq('node_locked', $e->completionError($graph, 'man', [], [], 'u'));
    eq('unknown_node', $e->completionError($graph, 'ghost', [], [], 'u'));

    foreach ([['>', 5, 4, true], ['>', 4, 4, false], ['>=', 4, 4, true], ['<', 3, 4, true], ['<=', 5, 4, false], ['==', 4, 4, true], ['!=', 4, 4, false]] as $c) {
        eq($c[3], ProgressEngine::compare((float)$c[1], $c[0], (float)$c[2]), 'op ' . $c[0]);
    }
});

test('auto-completion cascades to a fixed point + on_complete hook', function () {
    $calls = [];
    $st = makeSt(['on_complete' => function ($subject, $node, $tree) use (&$calls) {
        $calls[] = $subject . ':' . $node['uid'] . '@' . $tree['slug'];
    }]);
    $p = [
        'tree' => ['slug' => 'chain', 'name' => 'Chain'],
        'nodes' => [
            ['uid' => 'o', 'kind' => 'origin', 'name' => 'O'],
            ['uid' => 'a', 'parent' => 'o', 'name' => 'A', 'conditions' => [
                ['phase' => 'complete', 'type' => 'metric', 'params' => ['metric' => 'sales', 'op' => '>=', 'value' => 5]]]],
            ['uid' => 'b', 'parent' => 'a', 'name' => 'B', 'conditions' => [
                ['phase' => 'complete', 'type' => 'node', 'params' => ['node' => 'a']]]],
            ['uid' => 'c', 'parent' => 'b', 'name' => 'C', 'conditions' => [
                ['phase' => 'complete', 'type' => 'all', 'params' => ['nodes' => ['a', 'b']]]]],
            ['uid' => 'd', 'parent' => 'c', 'name' => 'D', 'conditions' => [
                ['phase' => 'complete', 'type' => 'any', 'params' => ['nodes' => ['c'], 'min' => 1]],
                ['phase' => 'complete', 'type' => 'manual']]],
            // hub completed by its children: children unlock with the origin (no deadlock)
            ['uid' => 'h', 'parent' => 'o', 'kind' => 'hub', 'name' => 'H', 'conditions' => [
                ['phase' => 'complete', 'type' => 'children', 'params' => ['min' => null]]]],
            ['uid' => 'h1', 'parent' => 'h', 'name' => 'H1', 'conditions' => [
                ['phase' => 'unlock', 'type' => 'node', 'params' => ['node' => 'o']],
                ['phase' => 'complete', 'type' => 'metric', 'params' => ['metric' => 'sales', 'op' => '>', 'value' => 1]]]],
        ],
        'links' => [],
    ];
    okData($st->handleArray('save', [], $p));
    okData($st->handleArray('complete', ['tree' => 'chain'], ['node' => 'o']));
    $data = okData($st->handleArray('metric', ['tree' => 'chain'], ['metric' => 'sales', 'value' => 5]));
    foreach (['o', 'a', 'b', 'c', 'h1', 'h'] as $u) {
        eq('completed', nodeBy($data, $u)['status'], "node $u");
    }
    eq('available', nodeBy($data, 'd')['status'], 'manual blocks auto-completion');
    eq(5, $data['metrics']['sales']);
    sort($calls);
    eq(['u1:a@chain', 'u1:b@chain', 'u1:c@chain', 'u1:h1@chain', 'u1:h@chain', 'u1:o@chain'], $calls);

    // cycle detection helper
    $e = new ProgressEngine();
    $cycles = $e->findCycles(g([
        ['uid' => 'o', 'kind' => 'origin'], ['uid' => 'x', 'parent' => 'o'], ['uid' => 'y', 'parent' => 'x'],
    ], [['uid' => 'l', 'from' => 'y', 'to' => 'x', 'type' => 'path']]));
    eq(1, count($cycles));
    $c = $cycles[0];
    sort($c);
    eq(['x', 'y'], $c);
    eq([], $e->findCycles(g([['uid' => 'o', 'kind' => 'origin'], ['uid' => 'x', 'parent' => 'o']])));
});

test('metric set / inc and evaluate action', function () {
    $st = makeSt();
    okData($st->handleArray('save', [], smallPayload()));
    $r = okData($st->handleArray('metric', [], ['metric' => 'posts', 'value' => 1, 'mode' => 'set']));
    eq(['metric' => 'posts', 'value' => 1], $r);
    $r = okData($st->handleArray('metric', [], ['metric' => 'posts', 'value' => 1.5, 'mode' => 'inc']));
    eq(2.5, $r['value']);
    $r = okData($st->handleArray('metric', [], ['metric' => 'posts', 'value' => 1, 'mode' => 'inc']));
    eq(3.5, $r['value']);
    eq(3.5, $st->progress()->getMetric('u1', 'posts'));
    $r = okData($st->handleArray('metric', [], ['metric' => 'posts', 'value' => '7', 'mode' => 'set']));
    eq(7, $r['value']);
    eq(400, $st->handleArray('metric', [], ['metric' => 'po sts', 'value' => 1])['status']);
    eq(400, $st->handleArray('metric', [], ['metric' => 'posts', 'value' => 'abc'])['status']);
    eq(400, $st->handleArray('metric', [], ['metric' => 'posts', 'value' => 1, 'mode' => 'mul'])['status']);

    // n1 needs posts >= 3 but is locked until o and h1 are completed
    $data = okData($st->handleArray('evaluate', ['tree' => 'love'], null));
    eq('locked', nodeBy($data, 'n1')['status']);
    okData($st->handleArray('complete', ['tree' => 'love'], ['node' => 'o']));
    $data = okData($st->handleArray('complete', ['tree' => 'love'], ['node' => 'h1']));
    eq('completed', nodeBy($data, 'n1')['status'], 'cascade after complete');
    eq('completed', nodeBy($data, 'n2')['status']);
    eq(7, $data['metrics']['posts']);

    // server-side helper
    $st2 = makeSt();
    okData($st2->handleArray('save', [], smallPayload()));
    $st2->complete('love', 'o');
    $st2->complete('love', 'h1');
    eq(4.0, $st2->metric('posts', 4));
    eq('completed', nodeBy($st2->tree('love'), 'n2')['status']);
});

test('complete refused when locked or conditions not met; uncomplete', function () {
    $st = makeSt();
    okData($st->handleArray('save', [], smallPayload()));
    $r = $st->handleArray('complete', ['tree' => 'love'], ['node' => 'h1'], 'POST');
    eq(false, $r['ok']);
    eq(400, $r['status']);
    eq('Node is locked', $r['error']);
    okData($st->handleArray('complete', ['tree' => 'love'], ['node' => 'o']));
    okData($st->handleArray('complete', ['tree' => 'love'], ['node' => 'h1']));
    $r = $st->handleArray('complete', ['tree' => 'love'], ['node' => 'n1']);
    eq('Completion conditions are not met', $r['error']);
    eq(404, $st->handleArray('complete', ['tree' => 'love'], ['node' => 'ghost'])['status']);
    eq(400, $st->handleArray('complete', ['tree' => 'love'], ['node' => "x' OR 1=1"])['status']);
    $data = okData($st->handleArray('complete', ['tree' => 'love'], ['node' => 'n3', 'data' => ['note' => 'validé']]));
    $n3 = nodeBy($data, 'n3');
    eq('completed', $n3['status']);
    ok(preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', (string)$n3['progress']['completed_at']) === 1, 'completed_at');
    eq(['note' => 'validé'], $n3['progress']['data']);
    $data = okData($st->handleArray('uncomplete', ['tree' => 'love'], ['node' => 'n3']));
    eq('available', nodeBy($data, 'n3')['status']);
});

test('permissions, methods and error codes', function () {
    $adapter = sqliteAdapter();
    $admin = makeSt([], $adapter);
    okData($admin->handleArray('save', [], smallPayload()));

    $viewer = new SuccessTree($adapter, ['subject' => 'u2', 'can_edit' => false, 'can_progress' => false]);
    $data = okData($viewer->handleArray('tree', ['tree' => 'love'], null, 'GET'));
    eq(['edit' => false, 'progress' => false], $data['permissions']);
    foreach (['save' => smallPayload(), 'delete' => [], 'install' => [], 'uncomplete' => ['node' => 'o'],
                 'complete' => ['node' => 'o'], 'metric' => ['metric' => 'm', 'value' => 1]] as $action => $body) {
        $r = $viewer->handleArray($action, ['tree' => 'love'], $body, 'POST');
        eq(403, $r['status'], $action);
        eq(false, $r['ok']);
    }
    // viewer cannot impersonate another subject
    $data = okData($viewer->handleArray('tree', ['tree' => 'love', 'subject' => 'u1'], null, 'GET'));
    eq('u2', $data['subject']);

    $player = new SuccessTree($adapter, ['subject' => 'u3', 'can_progress' => true]);
    okData($player->handleArray('complete', ['tree' => 'love'], ['node' => 'o'], 'POST'));
    eq(403, $player->handleArray('uncomplete', ['tree' => 'love'], ['node' => 'o'], 'POST')['status']);
    $anon = new SuccessTree($adapter, ['can_progress' => true]);
    eq(403, $anon->handleArray('complete', ['tree' => 'love'], ['node' => 'o'], 'POST')['status'], 'no subject');

    eq(405, $admin->handleArray('save', [], smallPayload(), 'GET')['status']);
    eq(405, $admin->handleArray('tree', ['tree' => 'love'], null, 'POST')['status']);
    eq(404, $admin->handleArray('nope', [], null, 'GET')['status']);
    eq(404, $admin->handleArray('tree', ['tree' => 'unknown'], null, 'GET')['status']);
    eq(400, $admin->handleArray('tree', [], null, 'GET')['status']);

    // via handle(): real Response objects
    $res = $viewer->handle(new Request('POST', ['action' => 'save'], smallPayload()));
    eq(403, $res->status());
    eq('{"ok":false,"error":"Forbidden"}', $res->body());
    eq('application/json; charset=utf-8', $res->header('Content-Type'));
    $res = $admin->handle(new Request('POST', ['action' => 'save'], null, [], true));
    eq(400, $res->status());
    $res = $admin->handle(new Request('GET', ['action' => 'tree', 'tree' => 'love']));
    eq(200, $res->status());
    $decoded = json_decode($res->body(), true);
    eq(true, $decoded['ok']);

    // delete
    okData($admin->handleArray('delete', ['tree' => 'love'], null, 'POST'));
    eq(404, $admin->handleArray('tree', ['tree' => 'love'])['status']);
    eq(0, count($adapter->select('SELECT * FROM `st_node`')));
    eq(0, count($adapter->select('SELECT * FROM `st_condition`')));
    eq(0, count($adapter->select('SELECT * FROM `st_progress`')));
});

test('internal errors are hidden unless debug', function () {
    $st = new SuccessTree(sqliteAdapter(), ['subject' => 'u']); // not installed
    $r = $st->handleArray('list', [], null, 'GET');
    eq(500, $r['status']);
    eq('Internal error', $r['error']);
    $st = new SuccessTree(sqliteAdapter(), ['subject' => 'u', 'debug' => true]);
    $r = $st->handleArray('list', [], null, 'GET');
    ok(strpos($r['error'], 'st_tree') !== false, 'debug shows details');
});

function injectionScenario(SuccessTree $st, string $label): void
{
    $evil = "Robert'); DROP TABLE st_node; -- \\' \" \\\\ \x1a \n end";
    $p = smallPayload();
    $p['tree']['name'] = $evil;
    $p['tree']['description'] = "O'Reilly \\";
    $p['nodes'][1]['name'] = $evil;
    $p['nodes'][1]['meta'] = ['k' => "a'b\\\"c"];
    $data = okData($st->handleArray('save', [], $p));
    eq($evil, $data['tree']['name'], $label . ' tree name');
    $data = okData($st->handleArray('tree', ['tree' => 'love']));
    eq($evil, nodeBy($data, 'h1')['name'], $label . ' node name');
    eq("O'Reilly \\", $data['tree']['description'], $label);
    eq(['k' => "a'b\\\"c"], nodeBy($data, 'h1')['meta'], $label);
    eq(404, $st->handleArray('tree', ['tree' => "love' OR '1'='1"])['status'], $label . ' slug injection');
    $p['nodes'][2]['uid'] = "n1' OR '1'='1";
    eq(400, $st->handleArray('save', [], $p)['status'], $label . ' uid injection');
    // subject injection (editor may target a subject)
    okData($st->handleArray('metric', ['subject' => "x' OR 1=1 --"], ['metric' => 'k', 'value' => 2]));
    eq(2.0, $st->progress()->getMetric("x' OR 1=1 --", 'k'), $label);
    eq(5, count(okData($st->handleArray('tree', ['tree' => 'love']))['nodes']), $label . ' table intact');
}

test('SQL injection is neutralised (PdoAdapter)', function () {
    injectionScenario(makeSt(), 'pdo');
});

test('ForceRequestAdapter: array / PDOStatement / objects / bool returns + PDO quote detection', function () {
    foreach (['array', 'statement', 'objects'] as $mode) {
        $host = FakeDatabase::fresh();
        $host->mode = $mode;
        $adapter = new ForceRequestAdapter(FakeDatabase::getInstance());
        eq('pdo', $adapter->escapeMode(), $mode);
        eq('sqlite', $adapter->driver(), 'driver detected from PDO');
        $st = makeSt([], $adapter);
        $data = okData($st->handleArray('save', [], smallPayload()));
        eq(5, count($data['nodes']), $mode);
        okData($st->handleArray('complete', ['tree' => 'love'], ['node' => 'o']));
        eq('completed', nodeBy(okData($st->handleArray('tree', ['tree' => 'love'])), 'o')['status'], $mode);
        injectionScenario($st, 'force-' . $mode);
        // grouped inserts: a single INSERT for the 5 nodes
        $inserts = array_filter($host->log, function ($sql) {
            return strpos($sql, 'INSERT INTO `st_node`') === 0;
        });
        ok(count($inserts) >= 1 && substr_count(reset($inserts), '), (') === 4, 'multi-VALUES insert');
    }
    eq([['a' => 1]], ForceRequestAdapter::normalizeRows(['a' => 1]), 'single row');
    eq([['a' => 1], ['a' => 2]], ForceRequestAdapter::normalizeRows([(object)['a' => 1], (object)['a' => 2]]));
    eq([], ForceRequestAdapter::normalizeRows(true));
    eq([], ForceRequestAdapter::normalizeRows(3));
    eq([['a' => 1]], ForceRequestAdapter::normalizeRows(new ArrayIterator([['a' => 1]])));
    $failing = new class {
        public function forceRequest($sql)
        {
            return false;
        }
    };
    throws(function () use ($failing) {
        (new ForceRequestAdapter($failing, ['driver' => 'mysql']))->exec('DELETE FROM x');
    }, RuntimeException::class);
    throws(function () {
        new ForceRequestAdapter(new stdClass());
    }, InvalidArgumentException::class);
});

test('ForceRequestAdapter: renamed method, manual escaping fallback, escape callable', function () {
    $adapter = new ForceRequestAdapter(new PrivateHost(), ['method' => 'query', 'driver' => 'sqlite']);
    eq('manual', $adapter->escapeMode());
    injectionScenario(makeSt([], $adapter), 'manual-sqlite');

    $cb = new ForceRequestAdapter(new PrivateHost(), ['method' => 'query', 'driver' => 'sqlite', 'escape' => function ($s) {
        return str_replace("'", "''", $s);
    }]);
    eq('callable', $cb->escapeMode());
    eq("'it''s'", $cb->quote("it's"));
    injectionScenario(makeSt([], $cb), 'callable');

    // MySQL manual escaping: a quote can never close the literal
    eq("'a''b\\\\c\\0\\n\\r\\\"\\Z'", AbstractAdapter::manualQuote("a'b\\c\0\n\r\"\x1a", 'mysql'));
    eq("'a''b\\c'", AbstractAdapter::manualQuote("a'b\\c", 'sqlite'));
    eq('NULL', $adapter->quote(null));
    eq('1', $adapter->quote(true));
    eq('42', $adapter->quote(42));
    eq('1.5', $adapter->quote(1.5));
    eq('NULL', $adapter->quote(INF));
});

test('Layout::heart — 9 symmetric points, tip at the bottom (y down)', function () {
    $h = Layout::heart(420);
    eq(9, count($h['points']));
    eq(['x' => 0.0, 'y' => 0.0], $h['center']);
    $tip = $h['points'][0];
    eq(0.0, $tip['x']);
    ok($tip['y'] > 0, 'tip below the origin');
    eq(round(17 / 16 * 420, 4), $tip['y']);
    foreach ($h['points'] as $i => $p) {
        ok($p['y'] <= $tip['y'], 'tip is the lowest point');
        if ($i > 0) {
            ok(abs($p['x']) > 1, 'no point on the axis besides the tip');
        }
    }
    for ($k = 1; $k <= 4; $k++) {
        $a = $h['points'][$k];
        $b = $h['points'][9 - $k];
        eq(-$a['x'], $b['x'], "pair $k mirrored x");
        eq($a['y'], $b['y'], "pair $k same y");
    }
    $top = min(array_column($h['points'], 'y'));
    ok($top < 0, 'upper lobes above the origin');
    ok(abs($h['points'][4]['x']) < abs($h['points'][3]['x']), 'k=4/5 frame the top dip');
    eq(420.0, max(array_map('abs', array_column(Layout::radial(4, 420), 'x'))));
    eq(6, count(Layout::ring([2, 4])));
    ok(TreeValidator::isUid(Layout::uid('hub')), 'uid helper');
    ok(Layout::uid() !== Layout::uid(), 'uid unique');
});

test('asset serving (Content-Type, ETag, 304, whitelist)', function () {
    $dir = sys_get_temp_dir() . '/st_assets_' . bin2hex(random_bytes(4));
    mkdir($dir);
    file_put_contents($dir . '/successtree.js', 'window.SuccessTree={};');
    file_put_contents($dir . '/successtree.css', ':root{--st-bg:#000}');
    $st = makeSt(['assets_dir' => $dir]);
    $res = $st->handle(new Request('GET', ['action' => 'asset', 'file' => 'successtree.js']));
    eq(200, $res->status());
    eq('application/javascript; charset=utf-8', $res->header('Content-Type'));
    eq('window.SuccessTree={};', $res->body());
    ok(strpos((string)$res->header('Cache-Control'), 'max-age=') !== false, 'cache header');
    $etag = (string)$res->header('ETag');
    ok($etag !== '', 'etag');
    $res = $st->handle(new Request('GET', ['action' => 'asset', 'file' => 'successtree.js'], null, ['If-None-Match' => $etag]));
    eq(304, $res->status());
    eq('', $res->body());
    $res = $st->handle(new Request('GET', ['action' => 'asset', 'file' => 'successtree.css']));
    eq('text/css; charset=utf-8', $res->header('Content-Type'));
    eq(404, $st->handle(new Request('GET', ['action' => 'asset', 'file' => '../src/SuccessTree.php']))->status());
    eq(404, $st->handle(new Request('GET', ['action' => 'asset', 'file' => 'other.js']))->status());
    eq(405, $st->handle(new Request('POST', ['action' => 'asset', 'file' => 'successtree.js']))->status());
    $empty = makeSt(['assets_dir' => $dir . '/missing']);
    eq(404, $empty->handle(new Request('GET', ['action' => 'asset', 'file' => 'successtree.js']))->status());
    unlink($dir . '/successtree.js');
    unlink($dir . '/successtree.css');
    rmdir($dir);
});

test('renderMount markup', function () {
    $st = makeSt(['endpoint' => '/api/tree?x=1', 'can_edit' => true, 'can_progress' => false]);
    $html = $st->renderMount('love"<', ['height' => '80vh', 'edit' => true]);
    ok(strpos($html, 'data-successtree') !== false, 'data attr');
    ok(strpos($html, 'data-endpoint="/api/tree?x=1"') !== false, 'endpoint');
    ok(strpos($html, 'data-tree="love&quot;&lt;"') !== false, 'escaped slug');
    ok(strpos($html, 'data-edit="1"') !== false, 'edit');
    ok(strpos($html, 'data-can-progress="1"') !== false, 'editors can progress');
    ok(strpos($html, 'style="height:80vh"') !== false, 'height');
    ok(strpos($html, 'href="/api/tree?x=1&amp;action=asset&amp;file=successtree.css') !== false, 'css via endpoint');
    ok(strpos($html, '<script src="/api/tree?x=1&amp;action=asset&amp;file=successtree.js') !== false, 'js via endpoint');
    $viewer = new SuccessTree(sqliteAdapter(), ['endpoint' => '/st']);
    $html = $viewer->renderMount('love', ['edit' => true, 'assets_url' => '/static/st/', 'height' => '1px;background:red']);
    ok(strpos($html, 'data-edit="0"') !== false, 'edit needs can_edit');
    ok(strpos($html, 'data-can-progress="0"') !== false, 'no progress');
    ok(strpos($html, 'href="/static/st/successtree.css"') !== false, 'assets_url css');
    ok(strpos($html, 'src="/static/st/successtree.js"') !== false, 'assets_url js');
    ok(strpos($html, 'style=') === false, 'unsafe height ignored');
    ok(strpos($viewer->renderMount('love', ['assets' => false]), '<script') === false, 'assets=false');
});

test('HeartDemo seed: valid, saved, playable', function () {
    $p = HeartDemo::payload();
    $valid = (new TreeValidator())->validate($p);
    $kinds = array_count_values(array_column($valid['nodes'], 'kind'));
    eq(1, $kinds['origin']);
    eq(10, $kinds['hub']);
    $byParent = [];
    foreach ($valid['nodes'] as $n) {
        $byParent[(string)$n['parent']][] = $n;
    }
    $colors = [];
    foreach ($valid['nodes'] as $n) {
        if ($n['kind'] !== 'hub') {
            continue;
        }
        $colors[$n['theme']['color']] = true;
        $branches = $byParent[$n['uid']];
        ok(count($branches) >= 2 && count($branches) <= 3, 'hub ' . $n['uid'] . ' has 2-3 branches');
        foreach ($branches as $root) {
            $depth = 1;
            $cur = $root['uid'];
            while (isset($byParent[$cur])) {
                $cur = $byParent[$cur][0]['uid'];
                $depth++;
            }
            ok($depth >= 2 && $depth <= 4, 'branch depth 2-4 (' . $root['uid'] . ": $depth)");
        }
        if ($n['uid'] !== 'h_coeur') {
            ok($n['x'] !== null && $n['y'] !== null, 'hub positioned');
        }
    }
    ok(count($colors) >= 6, 'varied hub colours');
    $types = [];
    foreach ($valid['nodes'] as $n) {
        foreach ($n['conditions'] as $c) {
            $types[$c['type']] = true;
        }
    }
    foreach (['metric', 'any', 'children', 'manual', 'all', 'node'] as $t) {
        ok(isset($types[$t]), "condition type $t used");
    }

    $st = makeSt();
    $data = okData($st->handleArray('save', [], $p));
    eq('heart-demo', $data['tree']['slug']);
    eq('available', nodeBy($data, 'origin')['status']);
    eq('locked', nodeBy($data, 'h_marketing')['status']);
    $data = okData($st->handleArray('complete', ['tree' => 'heart-demo'], ['node' => 'origin']));
    eq('available', nodeBy($data, 'h_marketing')['status']);
    eq('available', nodeBy($data, 'n_marketing_1_1')['status']);
    $data = okData($st->handleArray('metric', ['tree' => 'heart-demo'], ['metric' => 'followers', 'value' => 150]));
    okData($st->handleArray('complete', ['tree' => 'heart-demo'], ['node' => 'n_marketing_2_1']));
    $data = okData($st->handleArray('evaluate', ['tree' => 'heart-demo'], null));
    eq('completed', nodeBy($data, 'n_marketing_2_2')['status'], '100 followers auto');
    eq('available', nodeBy($data, 'n_marketing_2_3')['status']);
    // Marketing hub: children min 2
    okData($st->handleArray('complete', ['tree' => 'heart-demo'], ['node' => 'n_marketing_1_1']));
    eq('completed', nodeBy(okData($st->handleArray('tree', ['tree' => 'heart-demo'])), 'h_marketing')['status'], 'hub auto via children');
    $GLOBALS['__heart_example'] = $data;
});

// ---------------------------------------------------------------- run

$failed = 0;
$start = microtime(true);
foreach ($GLOBALS['__tests'] as $t) {
    list($name, $fn) = $t;
    try {
        $fn();
        echo "  \033[32m✔\033[0m $name\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  \033[31m✘ $name\033[0m\n      " . get_class($e) . ': ' . $e->getMessage() . "\n      at " . $e->getFile() . ':' . $e->getLine() . "\n";
    }
}
$total = count($GLOBALS['__tests']);
printf("\n%d/%d tests passed, %d assertions, %.0f ms (PHP %s)\n", $total - $failed, $total, $GLOBALS['__assertions'], (microtime(true) - $start) * 1000, PHP_VERSION);
exit($failed > 0 ? 1 : 0);
