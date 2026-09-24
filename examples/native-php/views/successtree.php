<?php
/** @var string $title  @var string $mount  @var array $user */
?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    body { margin: 0; font-family: system-ui, sans-serif; background: #1d1e45; color: #eef; }
    header { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; }
    header h1 { font-size: 1.1rem; margin: 0; }
    main { padding: 0 16px 16px; }
  </style>
</head>
<body>
  <header>
    <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <span><?= htmlspecialchars((string)($user['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
  </header>
  <main>
    <?= $mount /* HTML généré par SuccessTree::renderMount() : <div data-successtree> + <link> + <script> */ ?>
  </main>
</body>
</html>
