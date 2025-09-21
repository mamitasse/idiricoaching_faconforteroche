<?php
declare(strict_types=1);

use function App\services\csrf_input;
?>
<section class="form-wrap">
  <h1>Connexion</h1>

  <form method="post" action="<?= BASE_URL ?>?action=loginPost" class="form">
    <?= csrf_input() ?>

    <label class="form-field">
      <span>Email</span>
      <input type="email" name="email" autocomplete="email" required>
    </label>

    <label class="form-field">
      <span>Mot de passe</span>
      <input type="password" name="password" autocomplete="current-password" required>
    </label>

    <div class="form-actions">
      <a class="btn" href="<?= BASE_URL ?>?action=inscription">Créer un compte</a>
      <button type="submit" class="btn btn-primary">Se connecter</button>
    </div>
  </form>
</section>
