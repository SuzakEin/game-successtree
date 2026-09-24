<?php

declare(strict_types=1);

namespace SuccessTree\Repository;

use SuccessTree\Db\DbAdapterInterface;
use SuccessTree\Service\ValidationException;
use SuccessTree\Support\Json;

/**
 * CRUD for trees, nodes, links and conditions.
 * Table names come only from the (validated) prefix, never from user input.
 */
class TreeRepository
{
    const CHUNK = 100;

    /** @var DbAdapterInterface */
    private $db;

    /** @var string */
    private $prefix;

    /** @var bool */
    private $transactions;

    public function __construct(DbAdapterInterface $db, string $prefix = 'st_', bool $transactions = true)
    {
        if (!preg_match('/^[a-zA-Z0-9_]*$/', $prefix)) {
            throw new \InvalidArgumentException('Invalid table prefix');
        }
        $this->db = $db;
        $this->prefix = $prefix;
        $this->transactions = $transactions;
    }

    /** Backtick-quoted table name (backticks are accepted by both MySQL and SQLite). */
    public function table(string $name): string
    {
        return '`' . $this->prefix . $name . '`';
    }

    private function q($value): string
    {
        return $this->db->quote($value);
    }

    /** @return array<int, array<string, mixed>> */
    public function listTrees(): array
    {
        $rows = $this->db->select(
            'SELECT uid, slug, name, description, layout FROM ' . $this->table('tree') . ' ORDER BY name, slug'
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'uid' => (string)$r['uid'],
                'slug' => (string)$r['slug'],
                'name' => (string)$r['name'],
                'description' => $r['description'] === null ? '' : (string)$r['description'],
                'layout' => (string)$r['layout'],
            ];
        }
        return $out;
    }

    public function findBySlug(string $slug): ?array
    {
        $rows = $this->db->select('SELECT * FROM ' . $this->table('tree') . ' WHERE slug = ' . $this->q($slug) . ' LIMIT 1');
        return $rows ? $this->hydrateTree($rows[0]) : null;
    }

    public function findByUid(string $uid): ?array
    {
        $rows = $this->db->select('SELECT * FROM ' . $this->table('tree') . ' WHERE uid = ' . $this->q($uid) . ' LIMIT 1');
        return $rows ? $this->hydrateTree($rows[0]) : null;
    }

    /** Finds by slug first, then by uid. */
    public function find(string $ref): ?array
    {
        $t = $this->findBySlug($ref);
        return $t !== null ? $t : $this->findByUid($ref);
    }

    private function hydrateTree(array $r): array
    {
        return [
            'uid' => (string)$r['uid'],
            'slug' => (string)$r['slug'],
            'name' => (string)$r['name'],
            'description' => $r['description'] === null ? '' : (string)$r['description'],
            'layout' => (string)($r['layout'] !== null ? $r['layout'] : 'free'),
            'theme' => Json::decode($r['theme']),
            'settings' => Json::decode($r['settings']),
            'created_at' => $r['created_at'],
            'updated_at' => $r['updated_at'],
        ];
    }

    /**
     * Loads nodes (with their conditions) and links of a tree, keyed by insertion order.
     *
     * @return array{nodes: array<int, array>, links: array<int, array>}
     */
    public function loadGraph(string $treeUid): array
    {
        $tu = $this->q($treeUid);
        $nodeRows = $this->db->select(
            'SELECT * FROM ' . $this->table('node') . ' WHERE tree_uid = ' . $tu . ' ORDER BY sort, uid'
        );
        $condRows = $this->db->select(
            'SELECT c.* FROM ' . $this->table('condition') . ' c INNER JOIN ' . $this->table('node')
            . ' n ON n.uid = c.node_uid WHERE n.tree_uid = ' . $tu . ' ORDER BY c.node_uid, c.sort, c.uid'
        );
        $linkRows = $this->db->select(
            'SELECT * FROM ' . $this->table('link') . ' WHERE tree_uid = ' . $tu . ' ORDER BY uid'
        );

        $conds = [];
        foreach ($condRows as $c) {
            $conds[(string)$c['node_uid']][] = [
                'uid' => (string)$c['uid'],
                'phase' => (string)$c['phase'],
                'type' => (string)$c['type'],
                'params' => Json::decode($c['params']),
            ];
        }

        $nodes = [];
        foreach ($nodeRows as $r) {
            $uid = (string)$r['uid'];
            $nodes[] = [
                'uid' => $uid,
                'parent' => $r['parent_uid'] === null || $r['parent_uid'] === '' ? null : (string)$r['parent_uid'],
                'kind' => (string)$r['kind'],
                'slug' => $r['slug'] === null ? null : (string)$r['slug'],
                'name' => (string)$r['name'],
                'description' => $r['description'] === null ? '' : (string)$r['description'],
                'icon' => $r['icon'] === null ? null : (string)$r['icon'],
                'x' => $r['pos_x'] === null ? null : (float)$r['pos_x'],
                'y' => $r['pos_y'] === null ? null : (float)$r['pos_y'],
                'theme' => Json::decode($r['theme']),
                'reward' => Json::decode($r['reward']),
                'sort' => (int)$r['sort'],
                'meta' => Json::decode($r['meta']),
                'conditions' => isset($conds[$uid]) ? $conds[$uid] : [],
            ];
        }

        $links = [];
        foreach ($linkRows as $l) {
            $links[] = [
                'uid' => (string)$l['uid'],
                'from' => (string)$l['from_uid'],
                'to' => (string)$l['to_uid'],
                'type' => (string)$l['type'],
            ];
        }
        return ['nodes' => $nodes, 'links' => $links];
    }

    /**
     * Replaces a whole tree with a payload already normalised by TreeValidator.
     * Progress rows are kept for node uids that still exist and deleted for the others.
     *
     * @return string tree uid
     */
    public function save(array $payload, ?array $existing = null): string
    {
        $tree = $payload['tree'];
        $now = date('Y-m-d H:i:s');

        if ($existing === null && isset($tree['uid']) && $tree['uid'] !== null) {
            $existing = $this->findByUid((string)$tree['uid']);
        }
        $treeUid = $existing !== null ? $existing['uid']
            : (isset($tree['uid']) && $tree['uid'] !== null ? (string)$tree['uid'] : self::generateUid('t'));

        // Slug must not belong to another tree.
        $other = $this->findBySlug((string)$tree['slug']);
        if ($other !== null && $other['uid'] !== $treeUid) {
            throw new ValidationException(['tree.slug is already used by another tree']);
        }

        // Conditions / links without uid get one.
        $nodes = $payload['nodes'];
        foreach ($nodes as $i => $n) {
            foreach ($n['conditions'] as $j => $c) {
                if ($c['uid'] === null) {
                    $nodes[$i]['conditions'][$j]['uid'] = self::generateUid('c');
                }
            }
        }
        $links = $payload['links'];
        foreach ($links as $i => $l) {
            if ($l['uid'] === null) {
                $links[$i]['uid'] = self::generateUid('l');
            }
        }

        $this->assertUidsFree($treeUid, $nodes, $links);

        $this->begin();
        try {
            $tu = $this->q($treeUid);
            if ($existing !== null) {
                $this->db->exec(
                    'UPDATE ' . $this->table('tree') . ' SET slug = ' . $this->q($tree['slug'])
                    . ', name = ' . $this->q($tree['name'])
                    . ', description = ' . $this->q($tree['description'])
                    . ', layout = ' . $this->q($tree['layout'])
                    . ', theme = ' . $this->q(Json::encode($tree['theme']))
                    . ', settings = ' . $this->q(Json::encode($tree['settings']))
                    . ', updated_at = ' . $this->q($now)
                    . ' WHERE uid = ' . $tu
                );
            } else {
                $this->db->exec(
                    'INSERT INTO ' . $this->table('tree')
                    . ' (uid, slug, name, description, layout, theme, settings, created_at, updated_at) VALUES ('
                    . implode(', ', [
                        $tu, $this->q($tree['slug']), $this->q($tree['name']), $this->q($tree['description']),
                        $this->q($tree['layout']), $this->q(Json::encode($tree['theme'])),
                        $this->q(Json::encode($tree['settings'])), $this->q($now), $this->q($now),
                    ]) . ')'
                );
            }

            $this->deleteGraph($treeUid);

            $nodeValues = [];
            $condValues = [];
            $keep = [];
            foreach ($nodes as $n) {
                $keep[] = $this->q($n['uid']);
                $nodeValues[] = '(' . implode(', ', [
                    $this->q($n['uid']), $tu, $this->q($n['parent']), $this->q($n['kind']), $this->q($n['slug']),
                    $this->q($n['name']), $this->q($n['description']), $this->q($n['icon']),
                    $this->q($n['x']), $this->q($n['y']), $this->q(Json::encode($n['theme'])),
                    $this->q(Json::encode($n['reward'])), $this->q((int)$n['sort']), $this->q(Json::encode($n['meta'])),
                ]) . ')';
                foreach ($n['conditions'] as $k => $c) {
                    $condValues[] = '(' . implode(', ', [
                        $this->q($c['uid']), $this->q($n['uid']), $this->q($c['phase']), $this->q($c['type']),
                        $this->q(Json::encode($c['params'])), $this->q($k),
                    ]) . ')';
                }
            }
            $linkValues = [];
            foreach ($links as $l) {
                $linkValues[] = '(' . implode(', ', [
                    $this->q($l['uid']), $tu, $this->q($l['from']), $this->q($l['to']), $this->q($l['type']),
                ]) . ')';
            }

            $this->insertChunks('node', '(uid, tree_uid, parent_uid, kind, slug, name, description, icon, pos_x, pos_y, theme, reward, sort, meta)', $nodeValues);
            $this->insertChunks('condition', '(uid, node_uid, phase, type, params, sort)', $condValues);
            $this->insertChunks('link', '(uid, tree_uid, from_uid, to_uid, type)', $linkValues);

            // Progress: keep rows of surviving nodes only.
            $sql = 'DELETE FROM ' . $this->table('progress') . ' WHERE tree_uid = ' . $tu;
            if ($keep) {
                $sql .= ' AND node_uid NOT IN (' . implode(', ', $keep) . ')';
            }
            $this->db->exec($sql);

            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
        return $treeUid;
    }

    /** Deletes a tree with its nodes, links, conditions and progress. */
    public function delete(string $treeUid): void
    {
        $this->begin();
        try {
            $this->deleteGraph($treeUid);
            $tu = $this->q($treeUid);
            $this->db->exec('DELETE FROM ' . $this->table('progress') . ' WHERE tree_uid = ' . $tu);
            $this->db->exec('DELETE FROM ' . $this->table('tree') . ' WHERE uid = ' . $tu);
            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function deleteGraph(string $treeUid): void
    {
        $tu = $this->q($treeUid);
        $this->db->exec(
            'DELETE FROM ' . $this->table('condition') . ' WHERE node_uid IN (SELECT uid FROM '
            . $this->table('node') . ' WHERE tree_uid = ' . $tu . ')'
        );
        $this->db->exec('DELETE FROM ' . $this->table('link') . ' WHERE tree_uid = ' . $tu);
        $this->db->exec('DELETE FROM ' . $this->table('node') . ' WHERE tree_uid = ' . $tu);
    }

    /** @param string[] $values */
    private function insertChunks(string $table, string $columns, array $values): void
    {
        foreach (array_chunk($values, self::CHUNK) as $chunk) {
            $this->db->exec('INSERT INTO ' . $this->table($table) . ' ' . $columns . ' VALUES ' . implode(', ', $chunk));
        }
    }

    /** Node / link / condition uids are global primary keys: they must not belong to another tree. */
    private function assertUidsFree(string $treeUid, array $nodes, array $links): void
    {
        $tu = $this->q($treeUid);
        $errors = [];
        $nodeUids = [];
        $condUids = [];
        foreach ($nodes as $n) {
            $nodeUids[] = $this->q($n['uid']);
            foreach ($n['conditions'] as $c) {
                $condUids[] = $this->q($c['uid']);
            }
        }
        foreach (array_chunk($nodeUids, 500) as $chunk) {
            $rows = $this->db->select('SELECT uid FROM ' . $this->table('node') . ' WHERE tree_uid <> ' . $tu
                . ' AND uid IN (' . implode(', ', $chunk) . ') LIMIT 5');
            foreach ($rows as $r) {
                $errors[] = 'node uid "' . $r['uid'] . '" is already used by another tree';
            }
        }
        foreach (array_chunk($condUids, 500) as $chunk) {
            $rows = $this->db->select('SELECT c.uid FROM ' . $this->table('condition') . ' c INNER JOIN '
                . $this->table('node') . ' n ON n.uid = c.node_uid WHERE n.tree_uid <> ' . $tu
                . ' AND c.uid IN (' . implode(', ', $chunk) . ') LIMIT 5');
            foreach ($rows as $r) {
                $errors[] = 'condition uid "' . $r['uid'] . '" is already used by another tree';
            }
        }
        $linkUids = [];
        foreach ($links as $l) {
            $linkUids[] = $this->q($l['uid']);
        }
        foreach (array_chunk($linkUids, 500) as $chunk) {
            $rows = $this->db->select('SELECT uid FROM ' . $this->table('link') . ' WHERE tree_uid <> ' . $tu
                . ' AND uid IN (' . implode(', ', $chunk) . ') LIMIT 5');
            foreach ($rows as $r) {
                $errors[] = 'link uid "' . $r['uid'] . '" is already used by another tree';
            }
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
    }

    private function begin(): void
    {
        if (!$this->transactions) {
            return;
        }
        try {
            $this->db->exec($this->db->driver() === 'sqlite' ? 'BEGIN' : 'START TRANSACTION');
        } catch (\Throwable $e) {
            // Host connection without transaction support: continue "transactional-ish".
            $this->transactions = false;
        }
    }

    private function commit(): void
    {
        if ($this->transactions) {
            $this->db->exec('COMMIT');
        }
    }

    private function rollback(): void
    {
        if ($this->transactions) {
            try {
                $this->db->exec('ROLLBACK');
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    public static function generateUid(string $prefix = 'n'): string
    {
        return $prefix . '_' . bin2hex(random_bytes(6));
    }
}
