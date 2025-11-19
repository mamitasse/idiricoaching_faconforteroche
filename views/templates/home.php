<?php
declare(strict_types=1);
/**
 * Accueil : hero + deux cartes Coach avec images.
 * Les images doivent être présentes :
 *  - public/assets/images/nadiapagedaccueil.png
 *  - public/assets/images/sabrinapagedaccueil.png
 */
?>
<section class="hero">
  <h1>Bienvenue sur IdiriCoaching</h1>
  <p class="subtitle">Coaching avec Nadia &amp; Sabrina — réservez des créneaux en ligne.</p>
</section>

<section class="cards">
  <!-- Carte Nadia -->
  <article class="card">
    <h2>Nadia</h2>
    <a class="card-media" href="<?= BASE_URL ?>?action=nadia">
      <img src="<?= BASE_URL ?>assets/images/nadiapagedaccueil.png" alt="Coach Nadia">
    </a>
  
  </article>

  <!-- Carte Sabrina -->
  <article class="card">
    <h2>Sabrina</h2>
    <a class="card-media" href="<?= BASE_URL ?>?action=sabrina">
      <img src="<?= BASE_URL ?>assets/images/sabrinapagedaccueil.png" alt="Coach Sabrina">
    </a>
   
  </article>

  
</section>
 
<!-- Appelle ça juste après tes 2 cartes -->
<section class="home-cta">
  <a class="btn btn-primary" href="<?= BASE_URL ?>?action=inscription">Inscription</a>
  <a class="btn btn-primary" href="<?= BASE_URL ?>?action=connexion">Connexion</a>
</section>

