<?php

declare(strict_types=1);

/**
 * Mini front-controller du projet hôte.
 * Lancement : php -S localhost:8000 index.php   (depuis ce dossier)
 */

$config = require __DIR__ . '/config.php';

require $config['successtree']['src'] . '/autoload.php';
require __DIR__ . '/Database.php';
require __DIR__ . '/controllers/SuccessTreeController.php';

Database::configure($config['db']);

$path = rtrim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';

// Serveur PHP intégré : laisser passer les fichiers statiques réels éventuels.
if (PHP_SAPI === 'cli-server' && $path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

$controller = new SuccessTreeController($config);

try {
    switch ($path) {
        case '/':
            header('Location: ' . $config['successtree']['page']);
            break;
        case $config['successtree']['page']:
            $controller->page();
            break;
        case $config['successtree']['api']:
            $controller->api();
            break;
        default:
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Page introuvable';
    }
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    error_log((string)$e);
    echo 'Erreur serveur : ' . $e->getMessage();
}
