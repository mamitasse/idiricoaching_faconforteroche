<?php use function App\services\csrf_input; ?>
<section class="card">
  <h1>Connexion</h1>
  <form method="post" action="<?= BASE_URL ?>?action=loginPost">
    <?= csrf_input() ?>
    <label>Email
      <input type="email" name="email" required>
    </label>
    <label>Mot de passe
      <input type="password" name="password" required>
    </label>
    <button class="btn btn-primary" type="submit">Se connecter</button>
  </form>
</section>
