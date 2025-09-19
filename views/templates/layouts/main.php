<?php
use function App\services\e;
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= isset($title) ? e($title) : 'IdiriCoaching' ?></title>

  <!-- CSS (sert depuis /public/assets/style.css) -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/style.css<?php
    $disk = dirname(__DIR__, 3).'/public/assets/style.css'; // remonte à la racine -> public/assets/style.css
    if (is_file($disk)) echo '?v='.filemtime($disk);
  ?>">
</head>
<body>
  <header class="topbar">
    <a class="brand" href="<?= BASE_URL ?>">Idiri Coaching</a>
    <nav class="nav">
      <a href="<?= BASE_URL ?>">Accueil</a>
      <?php if (!empty($_SESSION['user'])): ?>
        <?php $u = $_SESSION['user']; ?>
        <span class="nav-user"><?= e($u['first_name'].' '.$u['last_name']) ?></span>
        <a href="<?= BASE_URL ?>?action=<?= $u['role']==='coach' ? 'coachDashboard' : 'adherentDashboard' ?>">Mon tableau de bord</a>
        <a href="<?= BASE_URL ?>?action=logout">Déconnexion</a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>?action=inscription">Inscription</a>
        <a href="<?= BASE_URL ?>?action=connexion">Connexion</a>
      <?php endif; ?>
    </nav>
  </header>

  <main class="container">
    <?php if (!empty($flashes)): ?>
      <?php foreach ($flashes as $type => $msgs): ?>
        <?php foreach ((array)$msgs as $msg): ?>
          <div class="flash <?= e($type) ?>"><?= e($msg) ?></div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    <?php endif; ?>

    <?= $content ?>
  </main>

  <footer class="footer">© <?= date('Y') ?> IdiriCoaching</footer>
</body>
</html>
