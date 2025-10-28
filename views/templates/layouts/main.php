<?php
declare(strict_types=1);
/** @var string   $content  HTML de la vue */
/** @var array    $flashes  messages flash groupés par type */
/** @var ?string  $title    titre de page optionnel */
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title><?= isset($title) ? htmlspecialchars($title, ENT_QUOTES) : 'Idiri Coaching' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Feuille de styles unique du site -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/style.css">
</head>
<body>

<!-- ===== En-tête / nav ===== -->
<header class="topbar">
  <a class="brand" href="<?= BASE_URL ?>">
    <img src="<?= BASE_URL ?>assets/images/logoIdiriCoaching.png" alt="Idiri Coaching" class="brand-logo">
    <span class="brand-text">Idiri Coaching</span>
  </a>

  <!-- Bouton burger (affiché en mobile via CSS) -->
  <button class="nav-toggle" type="button" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="site-menu">
    <span class="burger-line"></span>
    <span class="burger-line"></span>
    <span class="burger-line"></span>
  </button>

  <!-- Navigation principale -->
  <nav id="site-menu" class="nav">
  <a href="<?= BASE_URL ?>">Accueil</a>
  <a href="<?= BASE_URL ?>?action=services">Services</a>
  <a href="<?= BASE_URL ?>?action=nadia">Nadia</a>
  <a href="<?= BASE_URL ?>?action=sabrina">Sabrina</a>
  <a href="<?= BASE_URL ?>?action=contact">Contact</a>

  <?php if (empty($_SESSION['user'])): ?>
    <!-- Visiteurs : on propose inscription + connexion -->
    <a href="<?= BASE_URL ?>?action=inscription">Inscription</a>
    <a href="<?= BASE_URL ?>?action=connexion">Connexion</a>
  <?php else: ?>
    <!-- Connectés : lien vers le bon dashboard + déconnexion -->
    <?php if (($_SESSION['user']['role'] ?? '') === 'coach'): ?>
      <a href="<?= BASE_URL ?>?action=coachDashboard">Mon dashboard</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>?action=adherentDashboard">Mon dashboard</a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>?action=logout">Déconnexion</a>
  <?php endif; ?>
</nav>

</header>

<!-- ===== Contenu principal ===== -->
<main class="container">
  <!-- Messages flash (succès / erreurs) -->
  <?php if (!empty($flashes)): ?>
    <?php foreach ($flashes as $flashType => $messageList): ?>
      <?php foreach ($messageList as $message): ?>
        <div class="flash <?= htmlspecialchars((string)$flashType, ENT_QUOTES) ?>">
          <?= htmlspecialchars((string)$message, ENT_QUOTES) ?>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- HTML injecté depuis la vue -->
  <?= $content ?>
</main>

<!-- ===== Pied de page (partial dédié) ===== -->
 <?php
  // --- Include FOOTER (robuste) ---
  $footerPath = __DIR__ . '/../partial/footer.php';
  if (is_file($footerPath)) {
    include $footerPath;
  } elseif (defined('IN_DEV') && IN_DEV) {
    echo "<pre style='color:#f88'>[layout] Footer introuvable: $footerPath</pre>";
  }
  ?>

<!-- ===== JS pour le menu burger ===== -->
<script>
(function(){
  const burgerButton = document.querySelector('.nav-toggle');
  const siteMenu     = document.getElementById('site-menu');
  if(!burgerButton || !siteMenu) return;

  function toggleMenu(){
    const isOpen = siteMenu.classList.toggle('open');
    burgerButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  }
  burgerButton.addEventListener('click', toggleMenu);

  // Ferme le menu après clic sur un lien (en mobile)
  siteMenu.addEventListener('click', (event) => {
    if(event.target.tagName === 'A' && siteMenu.classList.contains('open')){
      siteMenu.classList.remove('open');
      burgerButton.setAttribute('aria-expanded','false');
    }
  });

  // Replie si on repasse en “desktop”
  window.addEventListener('resize', () => {
    if(window.innerWidth > 900 && siteMenu.classList.contains('open')){
      siteMenu.classList.remove('open');
      burgerButton.setAttribute('aria-expanded','false');
    }
  });
})();
</script>
</body>
</html>
