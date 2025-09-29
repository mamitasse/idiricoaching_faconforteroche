<?php
declare(strict_types=1);
/**
 * Page de connexion (utilise .form-wrap pour un rendu cohérent avec le CSS existant).
 * Action POST : ?action=loginPost (gérée dans AuthController::loginPost()).
 */
use function App\services\csrf_input;
?>
<section class="form-wrap">
  <h1>Connexion</h1>

  <form method="post" action="<?= BASE_URL ?>?action=loginPost" class="form">
    <?= csrf_input() ?>

    <label>Email
      <input type="email" name="email" required>
    </label>

    <label>Mot de passe
      <input type="password" name="password" required>
    </label>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Se connecter</button>
    </div>
  </form>
</section>
