<?php

declare(strict_types=1);

/**
 * Installe les tables SuccessTree et enregistre l'arbre démo « cœur ».
 * Usage : php seed.php
 */

use SuccessTree\Db\ForceRequestAdapter;
use SuccessTree\Seed\HeartDemo;
use SuccessTree\SuccessTree;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI uniquement.');
}

$config = require __DIR__ . '/config.php';
require $config['successtree']['src'] . '/autoload.php';
require __DIR__ . '/Database.php';

Database::configure($config['db']);
$db = Database::getInstance();

$st = new SuccessTree(
    new ForceRequestAdapter($db, ['escape' => [$db->getConnection(), 'real_escape_string'], 'driver' => 'mysql']),
    ['prefix' => $config['successtree']['prefix'], 'subject' => 'seed', 'can_edit' => true, 'driver' => 'mysql']
);

$check = static function (string $step, array $res): void {
    if (empty($res['ok'])) {
        fwrite(STDERR, "✗ $step : " . ($res['error'] ?? 'erreur inconnue') . PHP_EOL);
        exit(1);
    }
    echo "✓ $step" . PHP_EOL;
};

// 1. Tables (CREATE TABLE IF NOT EXISTS : relançable sans risque)
$check('Installation des tables', $st->handleArray('install', [], []));

// 2. Arbre démo cœur (9 hubs + 1 central), slug aligné sur la config
$payload = HeartDemo::payload();
$payload['tree']['slug'] = $config['successtree']['tree'];
$check('Sauvegarde de l\'arbre « ' . $payload['tree']['slug'] . ' »', $st->handleArray('save', ['tree' => $payload['tree']['slug']], $payload));

echo 'Terminé. Lancez : php -S localhost:8000 index.php puis ouvrez http://localhost:8000' . $config['successtree']['page'] . PHP_EOL;
