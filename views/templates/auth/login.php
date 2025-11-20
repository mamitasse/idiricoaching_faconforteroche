<?php

declare(strict_types=1);
/**
 * Page de connexion (utilise .form-wrap pour un rendu cohérent avec le CSS existant).
 * Action POST : ?action=loginPost (gérée dans AuthController::loginPost()).
 */

use function App\services\csrfInput;
?>
<section class="form-wrap">
  <h1>Connexion</h1>

  <form method="post" action="<?= BASE_URL ?>?action=loginPost" class="form">
    <?=csrfInput() ?>

    <label>Email
      <input type="email" name="email" required>
    </label>

    <label>Mot de passe
      <input type="password" name="password" required>
    </label>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Se connecter</button>
    </div>
    <p class="subtitle">
      <a href="<?= BASE_URL ?>?action=forgotPassword">Mot de passe oublié ?</a>
    </p>

  </form>
</section>