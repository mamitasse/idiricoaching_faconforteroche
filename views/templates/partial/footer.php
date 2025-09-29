<?php
declare(strict_types=1);
/**
 * Footer responsive + liens sociaux
 * - Les images doivent exister dans public/assets/images/
 * - Le style est dans assets/style.css (classes .social-media, .social-block, etc.)
 */
?>
<footer class="social-media">
  <div class="social-block social-linksnadia">
    <span class="name">Nadia</span>
    <div class="icons">
      <a href="https://www.facebook.com/idiri.nadia/" target="_blank" rel="noopener">
        <img src="<?= BASE_URL ?>assets/images/iconfacebook.png" alt="Facebook Nadia">
      </a>
      <a href="https://www.instagram.com/idirinadia" target="_blank" rel="noopener">
        <img src="<?= BASE_URL ?>assets/images/iconinstagram.png" alt="Instagram Nadia">
      </a>
    </div>
  </div>

  <div class="logo">
    <img src="<?= BASE_URL ?>assets/images/logoIdiriCoaching.png" alt="Idiri Coaching">
  </div>

  <div class="social-block social-linkssabrina">
    <span class="name">Sabrina</span>
    <div class="icons">
      <a href="https://www.facebook.com/sabrina.idiri" target="_blank" rel="noopener">
        <img src="<?= BASE_URL ?>assets/images/iconfacebook.png" alt="Facebook Sabrina">
      </a>
      <a href="https://www.instagram.com/sabrinaidiri/" target="_blank" rel="noopener">
        <img src="<?= BASE_URL ?>assets/images/iconinstagram.png" alt="Instagram Sabrina">
      </a>
    </div>
  </div>
</footer>
