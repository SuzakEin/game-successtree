<?php

/**
 * Configuration du projet hôte d'exemple.
 * Surchargez via variables d'environnement : DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME.
 */
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'name' => getenv('DB_NAME') ?: 'successtree_demo',
    ],
    'successtree' => [
        'src'    => __DIR__ . '/../../src',   // emplacement du paquet (copie ou submodule)
        'page'   => '/arbre',                 // route de la page
        'api'    => '/arbre/api',             // route unique déléguée à SuccessTree
        'tree'   => 'coeur',                  // slug de l'arbre affiché
        'prefix' => 'st_',
    ],
    // Utilisateur simulé : dans un vrai projet, il vient de votre session / auth.
    'demo_user' => [
        'id'       => 1,
        'name'     => 'Démo',
        'is_admin' => true,    // true => mode Construire autorisé (can_edit)
        'has_paid' => false,   // utilisé par la condition callback « has_paid »
    ],
];
