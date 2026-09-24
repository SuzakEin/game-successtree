<?php

declare(strict_types=1);

namespace SuccessTree\Repository;

use SuccessTree\Db\DbAdapterInterface;
use SuccessTree\Support\Json;

/**
 * Per-subject progress (completed nodes) and metrics.
 */
class ProgressRepository
{
    /** @var DbAdapterInterface */
    private $db;

    /** @var string */
    private $prefix;

    public function __construct(DbAdapterInterface $db, string $prefix = 'st_')
    {
        if (!preg_match('/^[a-zA-Z0-9_]*$/', $prefix)) {
            throw new \InvalidArgumentException('Invalid table prefix');
        }
        $this->db = $db;
        $this->prefix = $prefix;
    }

    private function table(string $name): string
    {
        return '`' . $this->prefix . $name . '`';
    }

    private function q($value): string
    {
        return $this->db->quote($value);
    }

    /**
     * @return array<string, array{completed_at: ?string, data: array}> keyed by node uid
     */
    public function completed(string $treeUid, string $subject): array
    {
        $rows = $this->db->select(
            'SELECT node_uid, completed_at, data FROM ' . $this->table('progress')
            . ' WHERE tree_uid = ' . $this->q($treeUid) . ' AND subject = ' . $this->q($subject)
            . " AND status = 'completed'"
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(string)$r['node_uid']] = [
                'completed_at' => $r['completed_at'] === null ? null : (string)$r['completed_at'],
                'data' => Json::decode($r['data']),
            ];
        }
        return $out;
    }

    public function markCompleted(string $treeUid, string $nodeUid, string $subject, ?array $data = null, ?string $at = null): string
    {
        $at = $at !== null ? $at : date('Y-m-d H:i:s');
        $this->db->exec(
            'DELETE FROM ' . $this->table('progress') . ' WHERE node_uid = ' . $this->q($nodeUid)
            . ' AND subject = ' . $this->q($subject)
        );
        $this->db->exec(
            'INSERT INTO ' . $this->table('progress') . ' (tree_uid, node_uid, subject, status, completed_at, data) VALUES ('
            . implode(', ', [
                $this->q($treeUid), $this->q($nodeUid), $this->q($subject), "'completed'", $this->q($at),
                $this->q($data === null ? null : Json::encode($data)),
            ]) . ')'
        );
        return $at;
    }

    public function uncomplete(string $treeUid, string $nodeUid, string $subject): void
    {
        $this->db->exec(
            'DELETE FROM ' . $this->table('progress') . ' WHERE tree_uid = ' . $this->q($treeUid)
            . ' AND node_uid = ' . $this->q($nodeUid) . ' AND subject = ' . $this->q($subject)
        );
    }

    /** @return array<string, float> */
    public function metrics(string $subject): array
    {
        $rows = $this->db->select(
            'SELECT metric, value FROM ' . $this->table('metric') . ' WHERE subject = ' . $this->q($subject)
            . ' ORDER BY metric'
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(string)$r['metric']] = (float)$r['value'];
        }
        return $out;
    }

    public function getMetric(string $subject, string $metric): ?float
    {
        $rows = $this->db->select(
            'SELECT value FROM ' . $this->table('metric') . ' WHERE subject = ' . $this->q($subject)
            . ' AND metric = ' . $this->q($metric) . ' LIMIT 1'
        );
        return $rows ? (float)$rows[0]['value'] : null;
    }

    /**
     * @param string $mode set|inc
     * @return float the new value
     */
    public function updateMetric(string $subject, string $metric, float $value, string $mode = 'set'): float
    {
        $current = $this->getMetric($subject, $metric);
        $now = $this->q(date('Y-m-d H:i:s'));
        if ($current === null) {
            $this->db->exec(
                'INSERT INTO ' . $this->table('metric') . ' (subject, metric, value, updated_at) VALUES ('
                . $this->q($subject) . ', ' . $this->q($metric) . ', ' . $this->q($value) . ', ' . $now . ')'
            );
            return $value;
        }
        $where = ' WHERE subject = ' . $this->q($subject) . ' AND metric = ' . $this->q($metric);
        if ($mode === 'inc') {
            $this->db->exec(
                'UPDATE ' . $this->table('metric') . ' SET value = value + ' . $this->q($value)
                . ', updated_at = ' . $now . $where
            );
            $new = $this->getMetric($subject, $metric);
            return $new === null ? $current + $value : $new;
        }
        $this->db->exec(
            'UPDATE ' . $this->table('metric') . ' SET value = ' . $this->q($value) . ', updated_at = ' . $now . $where
        );
        return $value;
    }
}
