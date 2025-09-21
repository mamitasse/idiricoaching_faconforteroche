<?php

declare(strict_types=1); ?>
<!doctype html>
<html lang="fr">

<head>
  <meta charset="utf-8">
  <title><?= isset($title) ? htmlspecialchars($title, ENT_QUOTES) : 'Idiri Coaching' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/style.css">
</head>

<body>
 <header class="topbar">
  <a class="brand" href="<?= BASE_URL ?>">
    <img src="<?= BASE_URL ?>assets/images/logoIdiriCoaching.png" alt="Idiri Coaching" class="brand-logo">
  </a>

  <!-- bouton burger (affiché seulement en mobile via CSS) -->
  <button class="nav-toggle" type="button" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="site-menu">
    <span class="burger-line"></span>
    <span class="burger-line"></span>
    <span class="burger-line"></span>
  </button>

  <!-- liens de navigation -->
  <nav id="site-menu" class="nav">
    <a href="<?= BASE_URL ?>">Accueil</a>
    <a href="<?= BASE_URL ?>?action=services">Services</a>
    <a href="<?= BASE_URL ?>?action=nadia">Nadia</a>
    <a href="<?= BASE_URL ?>?action=sabrina">Sabrina</a>

    <a href="<?= BASE_URL ?>?action=contact">Contact</a>
    <a href="<?= BASE_URL ?>?action=inscription">Inscription</a>
    <?php if (!empty($_SESSION['user'])): ?>
      <a href="<?= BASE_URL ?>?action=logout">Déconnexion</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>?action=connexion">Connexion</a>
    <?php endif; ?>
  </nav>
</header>

  <main class="container">
    <?php if (!empty($flashes ?? [])): ?>
      <?php foreach ($flashes as $type => $list): foreach ($list as $msg): ?>
          <div class="flash <?= htmlspecialchars($type) ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endforeach;
      endforeach; ?>
    <?php endif; ?>

    <?= $content ?? '' ?>
  </main>

  <?php
  // --- Include FOOTER (robuste) ---
  $footerPath = __DIR__ . '/../partial/footer.php';
  if (is_file($footerPath)) {
    include $footerPath;
  } elseif (defined('IN_DEV') && IN_DEV) {
    echo "<pre style='color:#f88'>[layout] Footer introuvable: $footerPath</pre>";
  }
  ?>
  <script>
  (function(){
    const btn  = document.querySelector('.nav-toggle');
    const menu = document.getElementById('site-menu');
    if(!btn || !menu) return;

    function toggle(){
      const open = menu.classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    btn.addEventListener('click', toggle);

    // Ferme le menu quand on clique un lien (en mobile)
    menu.addEventListener('click', (e) => {
      if(e.target.tagName === 'A' && menu.classList.contains('open')){
        menu.classList.remove('open');
        btn.setAttribute('aria-expanded','false');
      }
    });

    // Ferme si on redimensionne vers desktop
    window.addEventListener('resize', () => {
      if(window.innerWidth > 820 && menu.classList.contains('open')){
        menu.classList.remove('open');
        btn.setAttribute('aria-expanded','false');
      }
    });
  })();
</script>


</body>

</html>