<?php

declare(strict_types=1);

namespace SuccessTree\Service;

use SuccessTree\Support\Json;

/**
 * Validates and normalises the "save" payload sent by the editor (SPEC §4).
 * Computed keys (status, progress, subject, metrics, permissions) are ignored.
 */
class TreeValidator
{
    const UID_RE = '/^[A-Za-z0-9_\-:.]{1,64}$/';
    const SLUG_RE = '/^[A-Za-z0-9_\-.]{1,120}$/';
    const METRIC_RE = '/^[A-Za-z0-9_\-:.]{1,120}$/';
    const CALLBACK_RE = '/^[A-Za-z0-9_\-:.]{1,64}$/';

    const KINDS = ['origin', 'hub', 'node'];
    const LAYOUTS = ['free', 'heart', 'radial', 'ring'];
    const LINK_TYPES = ['path', 'visual'];
    const PHASES = ['unlock', 'complete'];
    const CONDITION_TYPES = ['parent', 'node', 'all', 'any', 'children', 'metric', 'manual', 'callback'];
    const OPERATORS = ['>', '>=', '<', '<=', '==', '!='];

    /** @var array<string, int> */
    private $limits = [
        'nodes' => 5000,
        'links' => 10000,
        'conditions_per_node' => 50,
        'condition_nodes' => 500,
        'name' => 190,
        'slug' => 120,
        'icon' => 64,
        'description' => 20000,
        'json' => 16384,
        'tree_json' => 65536,
    ];

    /** @var string[] */
    private $errors = [];

    public function __construct(array $limits = [])
    {
        foreach ($limits as $k => $v) {
            if (isset($this->limits[$k])) {
                $this->limits[$k] = (int)$v;
            }
        }
    }

    public static function isUid($v): bool
    {
        return is_string($v) && preg_match(self::UID_RE, $v) === 1;
    }

    /**
     * @return array normalised payload {tree, nodes, links}
     * @throws ValidationException
     */
    public function validate($payload): array
    {
        $this->errors = [];
        if (!is_array($payload)) {
            throw new ValidationException(['payload must be a JSON object']);
        }
        $tree = $this->validateTree(isset($payload['tree']) ? $payload['tree'] : null);

        $rawNodes = isset($payload['nodes']) ? $payload['nodes'] : null;
        if (!is_array($rawNodes) || !Json::isList($rawNodes)) {
            $this->errors[] = 'nodes must be an array';
            $rawNodes = [];
        }
        if (count($rawNodes) > $this->limits['nodes']) {
            throw new ValidationException(['too many nodes (max ' . $this->limits['nodes'] . ')']);
        }

        // Pass 1: uids
        $uids = [];
        foreach ($rawNodes as $i => $n) {
            if (is_array($n) && isset($n['uid']) && self::isUid($n['uid'])) {
                if (isset($uids[$n['uid']])) {
                    $this->errors[] = "nodes[$i].uid duplicated: " . $n['uid'];
                }
                $uids[$n['uid']] = true;
            }
        }

        $nodes = [];
        $origins = 0;
        foreach ($rawNodes as $i => $n) {
            $node = $this->validateNode($n, "nodes[$i]", $uids);
            if ($node === null) {
                continue;
            }
            if ($node['kind'] === 'origin') {
                $origins++;
            }
            $nodes[] = $node;
        }
        if ($origins !== 1) {
            $this->errors[] = 'exactly one node of kind "origin" is required (found ' . $origins . ')';
        }
        $this->checkParentCycles($nodes);

        $rawLinks = isset($payload['links']) ? $payload['links'] : [];
        if ($rawLinks === null) {
            $rawLinks = [];
        }
        if (!is_array($rawLinks) || !Json::isList($rawLinks)) {
            $this->errors[] = 'links must be an array';
            $rawLinks = [];
        }
        if (count($rawLinks) > $this->limits['links']) {
            throw new ValidationException(['too many links (max ' . $this->limits['links'] . ')']);
        }
        $links = [];
        $linkUids = [];
        foreach ($rawLinks as $i => $l) {
            $p = "links[$i]";
            if (!is_array($l)) {
                $this->errors[] = "$p must be an object";
                continue;
            }
            $uid = isset($l['uid']) ? $l['uid'] : null;
            if ($uid !== null && !self::isUid($uid)) {
                $this->errors[] = "$p.uid is invalid";
                continue;
            }
            if ($uid !== null) {
                if (isset($linkUids[$uid])) {
                    $this->errors[] = "$p.uid duplicated: $uid";
                }
                $linkUids[$uid] = true;
            }
            $from = isset($l['from']) ? $l['from'] : null;
            $to = isset($l['to']) ? $l['to'] : null;
            if (!self::isUid($from) || !isset($uids[$from])) {
                $this->errors[] = "$p.from must reference an existing node";
                continue;
            }
            if (!self::isUid($to) || !isset($uids[$to])) {
                $this->errors[] = "$p.to must reference an existing node";
                continue;
            }
            if ($from === $to) {
                $this->errors[] = "$p cannot link a node to itself";
                continue;
            }
            $type = isset($l['type']) && $l['type'] !== null ? $l['type'] : 'path';
            if (!in_array($type, self::LINK_TYPES, true)) {
                $this->errors[] = "$p.type must be path|visual";
                continue;
            }
            $links[] = ['uid' => $uid, 'from' => $from, 'to' => $to, 'type' => $type];
        }

        // Condition uids must be unique across the payload.
        $condUids = [];
        foreach ($nodes as $n) {
            foreach ($n['conditions'] as $c) {
                if ($c['uid'] !== null) {
                    if (isset($condUids[$c['uid']])) {
                        $this->errors[] = 'condition uid duplicated: ' . $c['uid'];
                    }
                    $condUids[$c['uid']] = true;
                }
            }
        }

        if ($this->errors) {
            throw new ValidationException($this->errors);
        }
        return ['tree' => $tree, 'nodes' => $nodes, 'links' => $links];
    }

    private function validateTree($t): array
    {
        $out = ['uid' => null, 'slug' => '', 'name' => '', 'description' => '', 'layout' => 'free', 'theme' => null, 'settings' => null];
        if (!is_array($t)) {
            $this->errors[] = 'tree must be an object';
            return $out;
        }
        if (isset($t['uid']) && $t['uid'] !== null && $t['uid'] !== '') {
            if (!self::isUid($t['uid'])) {
                $this->errors[] = 'tree.uid is invalid';
            } else {
                $out['uid'] = $t['uid'];
            }
        }
        $slug = isset($t['slug']) ? $t['slug'] : null;
        if (!is_string($slug) || !preg_match(self::SLUG_RE, $slug)) {
            $this->errors[] = 'tree.slug is required ([A-Za-z0-9_-.], max 120)';
        } else {
            $out['slug'] = $slug;
        }
        $out['name'] = $this->str($t, 'name', 'tree.name', $this->limits['name'], $out['slug']);
        $out['description'] = $this->str($t, 'description', 'tree.description', $this->limits['description'], '');
        $layout = isset($t['layout']) && $t['layout'] !== null ? $t['layout'] : 'free';
        if (!in_array($layout, self::LAYOUTS, true)) {
            $this->errors[] = 'tree.layout must be one of ' . implode('|', self::LAYOUTS);
        } else {
            $out['layout'] = $layout;
        }
        $out['theme'] = $this->json($t, 'theme', 'tree.theme', $this->limits['tree_json']);
        $out['settings'] = $this->json($t, 'settings', 'tree.settings', $this->limits['tree_json']);
        return $out;
    }

    private function validateNode($n, string $p, array $uids): ?array
    {
        if (!is_array($n)) {
            $this->errors[] = "$p must be an object";
            return null;
        }
        $uid = isset($n['uid']) ? $n['uid'] : null;
        if (!self::isUid($uid)) {
            $this->errors[] = "$p.uid is required and must match [A-Za-z0-9_-:.]{1,64}";
            return null;
        }
        $p = $p . '(' . $uid . ')';
        $kind = isset($n['kind']) ? $n['kind'] : 'node';
        if (!in_array($kind, self::KINDS, true)) {
            $this->errors[] = "$p.kind must be origin|hub|node";
            $kind = 'node';
        }
        $parent = array_key_exists('parent', $n) ? $n['parent'] : null;
        if ($parent === '') {
            $parent = null;
        }
        if ($parent !== null) {
            if (!self::isUid($parent) || !isset($uids[$parent])) {
                $this->errors[] = "$p.parent must reference an existing node";
                $parent = null;
            } elseif ($parent === $uid) {
                $this->errors[] = "$p.parent cannot be the node itself";
                $parent = null;
            } elseif ($kind === 'origin') {
                $this->errors[] = "$p: the origin cannot have a parent";
            }
        }
        $slug = isset($n['slug']) && $n['slug'] !== '' ? $n['slug'] : null;
        if ($slug !== null && (!is_string($slug) || !preg_match(self::SLUG_RE, $slug))) {
            $this->errors[] = "$p.slug is invalid";
            $slug = null;
        }
        $icon = isset($n['icon']) && $n['icon'] !== '' ? $n['icon'] : null;
        if ($icon !== null) {
            $icon = $this->str($n, 'icon', "$p.icon", $this->limits['icon'], null);
        }
        $node = [
            'uid' => $uid,
            'parent' => $parent,
            'kind' => $kind,
            'slug' => $slug,
            'name' => $this->str($n, 'name', "$p.name", $this->limits['name'], ''),
            'description' => $this->str($n, 'description', "$p.description", $this->limits['description'], ''),
            'icon' => $icon,
            'x' => $this->coord($n, 'x', $p),
            'y' => $this->coord($n, 'y', $p),
            'theme' => $this->json($n, 'theme', "$p.theme", $this->limits['json']),
            'reward' => $this->json($n, 'reward', "$p.reward", $this->limits['json']),
            'sort' => 0,
            'meta' => $this->json($n, 'meta', "$p.meta", $this->limits['json']),
            'conditions' => [],
        ];
        if (isset($n['sort']) && $n['sort'] !== null) {
            if (is_int($n['sort']) || (is_string($n['sort']) && preg_match('/^-?\d{1,9}$/', $n['sort'])) || (is_float($n['sort']) && floor($n['sort']) === $n['sort'] && abs($n['sort']) < 1e9)) {
                $node['sort'] = (int)$n['sort'];
            } else {
                $this->errors[] = "$p.sort must be an integer";
            }
        }
        $conds = isset($n['conditions']) ? $n['conditions'] : [];
        if ($conds === null) {
            $conds = [];
        }
        if (!is_array($conds) || !Json::isList($conds)) {
            $this->errors[] = "$p.conditions must be an array";
            $conds = [];
        }
        if (count($conds) > $this->limits['conditions_per_node']) {
            $this->errors[] = "$p has too many conditions (max " . $this->limits['conditions_per_node'] . ')';
            $conds = [];
        }
        foreach ($conds as $j => $c) {
            $cond = $this->validateCondition($c, "$p.conditions[$j]", $uids);
            if ($cond !== null) {
                $node['conditions'][] = $cond;
            }
        }
        return $node;
    }

    private function validateCondition($c, string $p, array $uids): ?array
    {
        if (!is_array($c)) {
            $this->errors[] = "$p must be an object";
            return null;
        }
        $uid = isset($c['uid']) && $c['uid'] !== '' ? $c['uid'] : null;
        if ($uid !== null && !self::isUid($uid)) {
            $this->errors[] = "$p.uid is invalid";
            return null;
        }
        $phase = isset($c['phase']) ? $c['phase'] : 'unlock';
        if (!in_array($phase, self::PHASES, true)) {
            $this->errors[] = "$p.phase must be unlock|complete";
            return null;
        }
        $type = isset($c['type']) ? $c['type'] : null;
        if (!is_string($type) || !in_array($type, self::CONDITION_TYPES, true)) {
            $this->errors[] = "$p.type is unknown (" . implode('|', self::CONDITION_TYPES) . ')';
            return null;
        }
        $params = isset($c['params']) ? $c['params'] : [];
        if ($params === null) {
            $params = [];
        }
        if (!is_array($params)) {
            $this->errors[] = "$p.params must be an object";
            return null;
        }
        $before = count($this->errors);
        switch ($type) {
            case 'node':
                if (!isset($params['node']) || !self::isUid($params['node']) || !isset($uids[$params['node']])) {
                    $this->errors[] = "$p.params.node must reference an existing node";
                }
                break;
            case 'all':
            case 'any':
                $list = isset($params['nodes']) ? $params['nodes'] : null;
                if (!is_array($list) || !$list || !Json::isList($list) || count($list) > $this->limits['condition_nodes']) {
                    $this->errors[] = "$p.params.nodes must be a non-empty array of node uids";
                    break;
                }
                foreach ($list as $u) {
                    if (!self::isUid($u) || !isset($uids[$u])) {
                        $this->errors[] = "$p.params.nodes references an unknown node";
                        break;
                    }
                }
                if ($type === 'any' && isset($params['min']) && $params['min'] !== null && (!is_int($params['min']) || $params['min'] < 1)) {
                    $this->errors[] = "$p.params.min must be an integer >= 1";
                }
                break;
            case 'children':
                if (isset($params['min']) && $params['min'] !== null && (!is_int($params['min']) || $params['min'] < 1)) {
                    $this->errors[] = "$p.params.min must be null or an integer >= 1";
                }
                break;
            case 'metric':
                if (!isset($params['metric']) || !is_string($params['metric']) || !preg_match(self::METRIC_RE, $params['metric'])) {
                    $this->errors[] = "$p.params.metric is invalid";
                }
                $op = isset($params['op']) ? $params['op'] : '>=';
                if (!in_array($op, self::OPERATORS, true)) {
                    $this->errors[] = "$p.params.op must be one of " . implode(' ', self::OPERATORS);
                }
                if (!isset($params['value']) || !is_numeric($params['value']) || is_string($params['value']) && !is_finite((float)$params['value'])) {
                    $this->errors[] = "$p.params.value must be a number";
                }
                break;
            case 'callback':
                if (!isset($params['name']) || !is_string($params['name']) || !preg_match(self::CALLBACK_RE, $params['name'])) {
                    $this->errors[] = "$p.params.name is invalid";
                }
                break;
        }
        if (count($this->errors) !== $before) {
            return null;
        }
        $enc = json_encode($params);
        if ($enc !== false && strlen($enc) > $this->limits['json']) {
            $this->errors[] = "$p.params is too large";
            return null;
        }
        return ['uid' => $uid, 'phase' => $phase, 'type' => $type, 'params' => $params];
    }

    private function str(array $a, string $key, string $label, int $max, ?string $default): ?string
    {
        if (!isset($a[$key]) || $a[$key] === null) {
            return $default;
        }
        $v = $a[$key];
        if (is_int($v) || is_float($v)) {
            $v = (string)$v;
        }
        if (!is_string($v)) {
            $this->errors[] = "$label must be a string";
            return $default;
        }
        if (!preg_match('//u', $v)) {
            $this->errors[] = "$label must be valid UTF-8";
            return $default;
        }
        $len = function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen((string)preg_replace('/[\x80-\xBF]/', '', $v));
        if ($len > $max) {
            $this->errors[] = "$label is too long (max $max)";
            return $default;
        }
        return $v;
    }

    private function coord(array $n, string $key, string $p): ?float
    {
        if (!isset($n[$key]) || $n[$key] === null || $n[$key] === '') {
            return null;
        }
        $v = $n[$key];
        if ((is_int($v) || is_float($v) || (is_string($v) && is_numeric($v))) && is_finite((float)$v) && abs((float)$v) < 1e9) {
            return (float)$v;
        }
        $this->errors[] = "$p.$key must be a finite number or null";
        return null;
    }

    private function json(array $a, string $key, string $label, int $max): ?array
    {
        if (!isset($a[$key]) || $a[$key] === null) {
            return null;
        }
        $v = $a[$key];
        if (is_object($v)) {
            $v = json_decode((string)json_encode($v), true);
        }
        if (!is_array($v)) {
            $this->errors[] = "$label must be a JSON object or array";
            return null;
        }
        $enc = json_encode($v, JSON_UNESCAPED_UNICODE);
        if ($enc === false) {
            $this->errors[] = "$label is not encodable as JSON";
            return null;
        }
        if (strlen($enc) > $max) {
            $this->errors[] = "$label is too large (max $max bytes)";
            return null;
        }
        return $v;
    }

    /** Parent chains must not loop. */
    private function checkParentCycles(array $nodes): void
    {
        $parent = [];
        foreach ($nodes as $n) {
            $parent[$n['uid']] = $n['parent'];
        }
        $state = []; // 1 = in progress, 2 = done
        foreach (array_keys($parent) as $start) {
            if (isset($state[$start])) {
                continue;
            }
            $path = [];
            $cur = $start;
            while ($cur !== null && array_key_exists($cur, $parent)) {
                if (isset($state[$cur])) {
                    if ($state[$cur] === 1) {
                        $this->errors[] = 'parent cycle detected at node ' . $cur;
                    }
                    break;
                }
                $state[$cur] = 1;
                $path[] = $cur;
                $cur = $parent[$cur];
            }
            foreach ($path as $u) {
                $state[$u] = 2;
            }
        }
    }
}

