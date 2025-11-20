<?php
declare(strict_types=1);
/** @var string|null $title */
use function App\services\csrfInput;
?>
<section class="card">
  <h1>Mot de passe oublié</h1>
  <p>Entrez votre e-mail. Si un compte existe, vous recevrez un lien de réinitialisation.</p>

 <form method="post" action="<?= BASE_URL ?>?action=forgotPasswordPost" class="form">

    <?= csrfInput() ?>
    <label>
      E-mail
      <input type="email" name="email" required>
    </label>
    <button class="btn" type="submit">Envoyer le lien</button>
  </form>
</section>
