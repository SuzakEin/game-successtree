<?php

declare(strict_types=1);

namespace SuccessTree\Service;

use SuccessTree\Repository\ProgressRepository;

/**
 * Status computation (locked | available | completed), condition evaluation (SPEC §5),
 * cascading auto-completion up to a fixed point, and the host on_complete hook.
 *
 * Graph format = TreeRepository::loadGraph(): ['nodes' => [...], 'links' => [...]].
 * $completed = map node uid => anything (only keys matter).
 *
 * Implicit "parent" prerequisite of a node = its parent_uid + the sources of every incoming
 * link of type "path" (multi-parents). A node without parent nor incoming path link depends
 * on the origin. The origin itself is always available.
 */
class ProgressEngine
{
    /** @var array<string, callable> */
    private $callbacks;

    /** @var callable|null */
    private $onComplete;

    /** @var ProgressRepository|null */
    private $progress;

    public function __construct(?ProgressRepository $progress = null, array $callbacks = [], ?callable $onComplete = null)
    {
        $this->progress = $progress;
        $this->callbacks = $callbacks;
        $this->onComplete = $onComplete;
    }

    // ------------------------------------------------------------------ pure computation

    /**
     * @return array<string, string> node uid => locked|available|completed
     */
    public function statuses(array $graph, array $completed, array $metrics, string $subject): array
    {
        $idx = $this->index($graph);
        $out = [];
        foreach ($idx['nodes'] as $uid => $node) {
            if (isset($completed[$uid])) {
                $out[$uid] = 'completed';
            } elseif ($this->isAvailable($idx, $node, $completed, $metrics, $subject)) {
                $out[$uid] = 'available';
            } else {
                $out[$uid] = 'locked';
            }
        }
        return $out;
    }

    /**
     * Pure cascade: returns the uids that become completed automatically, in completion order.
     * Terminates because the completed set only grows (guarded by an iteration cap anyway).
     *
     * @return string[]
     */
    public function autoCompletions(array $graph, array $completed, array $metrics, string $subject): array
    {
        $idx = $this->index($graph);
        $new = [];
        $max = count($idx['nodes']) + 1;
        for ($pass = 0; $pass < $max; $pass++) {
            $changed = false;
            foreach ($idx['nodes'] as $uid => $node) {
                if (isset($completed[$uid])) {
                    continue;
                }
                if (!$this->isAutoCompletable($idx, $node, $completed, $metrics, $subject)) {
                    continue;
                }
                $completed[$uid] = true;
                $new[] = $uid;
                $changed = true;
            }
            if (!$changed) {
                break;
            }
        }
        return $new;
    }

    /**
     * Can the node be completed explicitly (API "complete")?
     * Returns null when allowed, otherwise an error code: node_locked | conditions_not_met | unknown_node.
     */
    public function completionError(array $graph, string $uid, array $completed, array $metrics, string $subject): ?string
    {
        $idx = $this->index($graph);
        if (!isset($idx['nodes'][$uid])) {
            return 'unknown_node';
        }
        $node = $idx['nodes'][$uid];
        if (isset($completed[$uid])) {
            return null;
        }
        if (!$this->isAvailable($idx, $node, $completed, $metrics, $subject)) {
            return 'node_locked';
        }
        foreach ($node['conditions'] as $c) {
            if ($c['phase'] !== 'complete') {
                continue;
            }
            if (!$this->evalCondition($idx, $node, $c, $completed, $metrics, $subject, true)) {
                return 'conditions_not_met';
            }
        }
        return null;
    }

    /**
     * Cycles in the prerequisite graph (parent + path links). Nodes of such a cycle without explicit
     * unlock conditions can never become available.
     *
     * @return array<int, string[]>
     */
    public function findCycles(array $graph): array
    {
        $idx = $this->index($graph);
        $color = [];
        $stack = [];
        $cycles = [];
        $seen = [];
        foreach (array_keys($idx['nodes']) as $start) {
            if (isset($color[$start])) {
                continue;
            }
            // iterative DFS over prerequisite edges (node -> its prerequisites)
            $work = [[$start, 0]];
            while ($work) {
                $top = count($work) - 1;
                list($u, $i) = $work[$top];
                if ($i === 0 && !isset($color[$u])) {
                    $color[$u] = 1;
                    $stack[] = $u;
                }
                $prereqs = $this->prerequisites($idx, $u);
                if ($i < count($prereqs)) {
                    $work[$top][1] = $i + 1;
                    $v = $prereqs[$i];
                    if (!isset($idx['nodes'][$v])) {
                        continue;
                    }
                    if (!isset($color[$v])) {
                        $work[] = [$v, 0];
                    } elseif ($color[$v] === 1) {
                        $pos = array_search($v, $stack, true);
                        $cycle = array_slice($stack, (int)$pos);
                        $key = implode('|', $this->sorted($cycle));
                        if (!isset($seen[$key])) {
                            $seen[$key] = true;
                            $cycles[] = $cycle;
                        }
                    }
                } else {
                    $color[$u] = 2;
                    array_pop($stack);
                    array_pop($work);
                }
            }
        }
        return $cycles;
    }

    // ------------------------------------------------------------------ persistence + hook

    /**
     * Runs the auto-completion cascade for a subject, persists it and fires on_complete.
     *
     * @return string[] newly completed uids
     */
    public function evaluate(array $tree, array $graph, string $subject, array $completed, array $metrics): array
    {
        $new = $this->autoCompletions($graph, $completed, $metrics, $subject);
        $this->persist($tree, $graph, $subject, $new, null);
        return $new;
    }

    /**
     * Explicit completion then cascade.
     *
     * @return string[] every newly completed uid (the node first)
     * @throws \DomainException with code node_locked | conditions_not_met | unknown_node
     */
    public function complete(array $tree, array $graph, string $subject, string $uid, array $completed, array $metrics, ?array $data = null): array
    {
        if (isset($completed[$uid])) {
            return [];
        }
        $err = $this->completionError($graph, $uid, $completed, $metrics, $subject);
        if ($err !== null) {
            throw new \DomainException($err);
        }
        $this->persist($tree, $graph, $subject, [$uid], $data);
        $completed[$uid] = true;
        $cascade = $this->autoCompletions($graph, $completed, $metrics, $subject);
        $this->persist($tree, $graph, $subject, $cascade, null);
        return array_merge([$uid], $cascade);
    }

    private function persist(array $tree, array $graph, string $subject, array $uids, ?array $data): void
    {
        if (!$uids) {
            return;
        }
        $byUid = [];
        foreach ($graph['nodes'] as $n) {
            $byUid[$n['uid']] = $n;
        }
        foreach ($uids as $uid) {
            if ($this->progress !== null) {
                $this->progress->markCompleted($tree['uid'], $uid, $subject, $data);
            }
            if ($this->onComplete !== null && isset($byUid[$uid])) {
                call_user_func($this->onComplete, $subject, $byUid[$uid], $tree);
            }
        }
    }

    // ------------------------------------------------------------------ internals

    private function index(array $graph): array
    {
        $nodes = [];
        $children = [];
        $pathIn = [];
        $origin = null;
        foreach ($graph['nodes'] as $n) {
            $nodes[$n['uid']] = $n;
            if ($n['kind'] === 'origin' && $origin === null) {
                $origin = $n['uid'];
            }
        }
        foreach ($nodes as $uid => $n) {
            if ($n['parent'] !== null && isset($nodes[$n['parent']])) {
                $children[$n['parent']][] = $uid;
            }
        }
        $links = isset($graph['links']) ? $graph['links'] : [];
        foreach ($links as $l) {
            if ($l['type'] === 'path' && isset($nodes[$l['from']]) && isset($nodes[$l['to']])) {
                $pathIn[$l['to']][] = $l['from'];
            }
        }
        return ['nodes' => $nodes, 'children' => $children, 'pathIn' => $pathIn, 'origin' => $origin];
    }

    /** @return string[] */
    private function prerequisites(array $idx, string $uid): array
    {
        $node = $idx['nodes'][$uid];
        if ($node['kind'] === 'origin') {
            return [];
        }
        $pre = [];
        if ($node['parent'] !== null && isset($idx['nodes'][$node['parent']])) {
            $pre[] = $node['parent'];
        }
        if (isset($idx['pathIn'][$uid])) {
            foreach ($idx['pathIn'][$uid] as $from) {
                if (!in_array($from, $pre, true)) {
                    $pre[] = $from;
                }
            }
        }
        if (!$pre && $idx['origin'] !== null && $idx['origin'] !== $uid) {
            $pre[] = $idx['origin'];
        }
        return $pre;
    }

    private function isAvailable(array $idx, array $node, array $completed, array $metrics, string $subject): bool
    {
        if ($node['kind'] === 'origin') {
            return true;
        }
        $hasUnlock = false;
        foreach ($node['conditions'] as $c) {
            if ($c['phase'] !== 'unlock') {
                continue;
            }
            $hasUnlock = true;
            if (!$this->evalCondition($idx, $node, $c, $completed, $metrics, $subject, false)) {
                return false;
            }
        }
        if (!$hasUnlock) {
            return $this->evalCondition($idx, $node, ['type' => 'parent', 'params' => []], $completed, $metrics, $subject, false);
        }
        return true;
    }

    private function isAutoCompletable(array $idx, array $node, array $completed, array $metrics, string $subject): bool
    {
        $count = 0;
        foreach ($node['conditions'] as $c) {
            if ($c['phase'] !== 'complete') {
                continue;
            }
            if ($c['type'] === 'manual') {
                return false;
            }
            $count++;
        }
        if ($count === 0) {
            return false;
        }
        if (!$this->isAvailable($idx, $node, $completed, $metrics, $subject)) {
            return false;
        }
        foreach ($node['conditions'] as $c) {
            if ($c['phase'] === 'complete' && !$this->evalCondition($idx, $node, $c, $completed, $metrics, $subject, false)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param bool $explicit true during an explicit "complete" call: "manual" is then satisfied.
     */
    private function evalCondition(array $idx, array $node, array $c, array $completed, array $metrics, string $subject, bool $explicit): bool
    {
        $p = isset($c['params']) && is_array($c['params']) ? $c['params'] : [];
        switch ($c['type']) {
            case 'parent':
                foreach ($this->prerequisites($idx, $node['uid']) as $u) {
                    if (!isset($completed[$u])) {
                        return false;
                    }
                }
                return true;
            case 'node':
                return isset($p['node']) && is_string($p['node']) && isset($completed[$p['node']]);
            case 'all':
                if (empty($p['nodes']) || !is_array($p['nodes'])) {
                    return false;
                }
                foreach ($p['nodes'] as $u) {
                    if (!is_string($u) || !isset($completed[$u])) {
                        return false;
                    }
                }
                return true;
            case 'any':
                if (empty($p['nodes']) || !is_array($p['nodes'])) {
                    return false;
                }
                $min = isset($p['min']) && is_numeric($p['min']) ? max(1, (int)$p['min']) : 1;
                $n = 0;
                foreach ($p['nodes'] as $u) {
                    if (is_string($u) && isset($completed[$u])) {
                        $n++;
                    }
                }
                return $n >= $min;
            case 'children':
                $kids = isset($idx['children'][$node['uid']]) ? $idx['children'][$node['uid']] : [];
                if (!$kids) {
                    return false; // no children: never true (avoids accidental auto-completion)
                }
                $n = 0;
                foreach ($kids as $k) {
                    if (isset($completed[$k])) {
                        $n++;
                    }
                }
                if (isset($p['min']) && is_numeric($p['min'])) {
                    return $n >= max(1, (int)$p['min']);
                }
                return $n === count($kids);
            case 'metric':
                if (!isset($p['metric']) || !is_string($p['metric'])) {
                    return false;
                }
                $v = isset($metrics[$p['metric']]) ? (float)$metrics[$p['metric']] : 0.0;
                $target = isset($p['value']) && is_numeric($p['value']) ? (float)$p['value'] : 0.0;
                $op = isset($p['op']) ? (string)$p['op'] : '>=';
                return self::compare($v, $op, $target);
            case 'manual':
                return $explicit;
            case 'callback':
                $name = isset($p['name']) ? (string)$p['name'] : '';
                if ($name === '' || !isset($this->callbacks[$name]) || !is_callable($this->callbacks[$name])) {
                    return false;
                }
                try {
                    return (bool)call_user_func($this->callbacks[$name], $subject, $node, $p);
                } catch (\Throwable $e) {
                    return false;
                }
        }
        return false;
    }

    public static function compare(float $a, string $op, float $b): bool
    {
        $eps = 1e-9;
        switch ($op) {
            case '>':
                return $a > $b;
            case '>=':
                return $a >= $b - $eps;
            case '<':
                return $a < $b;
            case '<=':
                return $a <= $b + $eps;
            case '==':
                return abs($a - $b) < $eps;
            case '!=':
                return abs($a - $b) >= $eps;
        }
        return false;
    }

    private function sorted(array $a): array
    {
        sort($a);
        return $a;
    }
}
