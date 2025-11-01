<?php
declare(strict_types=1);
/** @var string $token */
use function App\services\csrf_input;
use function App\services\e;
?>
<section class="card" style="max-width:520px;margin:auto">
  <h1>Réinitialiser le mot de passe</h1>

  <form method="post" action="<?= BASE_URL ?>?action=resetPasswordPost" class="form">
    <?= csrf_input() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <label>
      Nouveau mot de passe
      <input type="password" name="password" minlength="8" required>
    </label>

    <label>
      Confirmer le mot de passe
      <input type="password" name="password_confirm" minlength="8" required>
    </label>

    <button class="btn" type="submit">Valider</button>
  </form>
</section>
