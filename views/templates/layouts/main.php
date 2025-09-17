<?php
use function App\services\e;
$flash = $flash ?? [];
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($title ?? 'Idiri Coaching') ?></title>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>assets/style.css">
  <style>
    body{background:#1f1718;color:#eee;font-family:system-ui;margin:0}
    header,main,footer{max-width:1100px;margin:20px auto;padding:16px;border-radius:12px;background:#4c2f31}
    a{color:#ffd;text-decoration:none;margin-right:10px}
    .flash{padding:10px;border-radius:8px;margin:8px 0}
    .flash.success{background:#2b7a2b}.flash.error{background:#8a2a2a}
    .btn{background:#7a524f;color:#fff;border:0;border-radius:8px;padding:8px 12px;cursor:pointer}
    input,select{border-radius:8px;border:1px solid #333;background:#2b2122;color:#eee;padding:8px}
    .card{background:#3d2a2c;border-radius:12px;padding:12px;margin:12px 0}
    .grid{display:grid;gap:8px}
  </style>
</head>
<body>
<header>
  <b>Idiri Coaching</b>
  <nav style="float:right">
    <a href="<?= e(BASE_URL) ?>">Accueil</a>
    <?php if (!empty($_SESSION['user'])): ?>
      <?php if (($_SESSION['user']['role'] ?? '')==='coach'): ?>
        <a href="<?= e(BASE_URL) ?>?action=coachDashboard">Mon tableau de bord</a>
      <?php else: ?>
        <a href="<?= e(BASE_URL) ?>?action=adherentDashboard">Mon tableau de bord</a>
      <?php endif; ?>
      <a href="<?= e(BASE_URL) ?>?action=logout">Déconnexion</a>
    <?php else: ?>
      <a href="<?= e(BASE_URL) ?>?action=inscription">Inscription</a>
      <a href="<?= e(BASE_URL) ?>?action=connexion">Connexion</a>
    <?php endif; ?>
  </nav>
  <div style="clear:both"></div>
</header>
<main>
  <?php foreach (($flash ?? []) as $type => $msgs): foreach ($msgs as $m): ?>
    <div class="flash <?= e($type) ?>"><?= e($m) ?></div>
  <?php endforeach; endforeach; ?>
  <?= $content ?? '' ?>
</main>
<footer><small>© <?= date('Y') ?> IdiriCoaching</small></footer>
</body>
</html>
