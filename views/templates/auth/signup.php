<?php
use function App\services\csrf_input;
use function App\services\e;
/** @var \App\models\UserEntity[] $coachEntities */
?>
<section class="form-wrap">
  <h1>Inscription</h1>

  <form method="post" action="<?= BASE_URL ?>?action=signupPost" class="form">
    <?= csrf_input() ?>

    <div class="grid-2">
      <label>Prénom
        <input name="first_name" required>
      </label>
      <label>Nom
        <input name="last_name" required>
      </label>
    </div>

    <div class="grid-2">
      <label>Email
        <input type="email" name="email" required>
      </label>
      <label>Confirmation email
        <input type="email" name="email_confirm" required>
      </label>
    </div>

    <div class="grid-2">
      <label>Téléphone
        <input name="phone" placeholder="06 12 34 56 78" pattern="^\+?[0-9 \-\.]{9,20}$" required>
      </label>
      <label>Adresse
        <input name="address" placeholder="N° et rue, CP Ville" minlength="5" maxlength="255" required>
      </label>
    </div>

    <div class="grid-2">
      <label>Mot de passe
        <input type="password" name="password" required>
      </label>
      <label>Confirmation
        <input type="password" name="password_confirm" required>
      </label>
    </div>

    <div class="grid-3">
      <label>Âge
        <input type="number" name="age" min="12" max="100">
      </label>

      <label>Genre
        <select name="gender">
          <option value="">—</option>
          <option value="female">Femme</option>
          <option value="male">Homme</option>
          <option value="other">Autre</option>
        </select>
      </label>

      <label>Rôle
        <select name="role" id="role-select">
          <option value="adherent">Adhérent</option>
          <option value="coach">Coach</option>
        </select>
      </label>
    </div>

    <div id="coach-chooser">
      <label>Choisir un coach (obligatoire pour adhérent)
        <select name="coach_id">
          <option value="">— Sélectionner —</option>
          <?php foreach ($coachEntities as $e): ?>
            <option value="<?= (int)$e->getId() ?>"><?= e($e->getFullName()) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <button class="btn btn-primary" type="submit">Créer mon compte</button>
  </form>
</section>

<script>
  const roleSelect = document.getElementById('role-select');
  const coachChooser = document.getElementById('coach-chooser');
  function toggleCoach(){ coachChooser.style.display = (roleSelect.value==='coach') ? 'none' : 'block'; }
  roleSelect?.addEventListener('change', toggleCoach); toggleCoach();
</script>
