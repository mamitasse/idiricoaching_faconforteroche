<?php use function App\services\csrf_input; use function App\services\e; ?>
<section class="card">
  <h1>Inscription</h1>
  <form method="post" action="<?= BASE_URL ?>?action=signupPost">
    <?= csrf_input() ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <label>Prénom<input name="first_name" required></label>
      <label>Nom<input name="last_name" required></label>
    </div>
    <label>Email<input type="email" name="email" required></label>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <label>Téléphone<input name="phone" placeholder="06 12 34 56 78"></label>
      <label>Adresse<input name="address" placeholder="N° et rue, CP Ville"></label>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <label>Mot de passe<input type="password" name="password" required></label>
      <label>Âge<input type="number" name="age" min="12" max="100"></label>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <label>Genre
        <select name="gender">
          <option value="">—</option><option value="female">Femme</option>
          <option value="male">Homme</option><option value="other">Autre</option>
        </select>
      </label>
      <label>Rôle
        <select name="role" id="role-select">
          <option value="adherent">Adhérent</option>
          <option value="coach">Coach</option>
        </select>
      </label>
    </div>
    <?php if (!empty($coaches)): ?>
    <div id="coach-chooser">
      <label>Choisir un coach (obligatoire pour adhérent)
        <select name="coach_id">
          <option value="">— Sélectionner —</option>
          <?php foreach ($coaches as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['first_name'].' '.$c['last_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <?php endif; ?>
    <button class="btn btn-primary" type="submit">Créer mon compte</button>
  </form>
</section>
<script>
  const roleSelect = document.getElementById('role-select');
  const coachChooser = document.getElementById('coach-chooser');
  function toggleCoach(){ if(!coachChooser) return; coachChooser.style.display = (roleSelect.value==='coach')?'none':'block'; }
  roleSelect?.addEventListener('change', toggleCoach); toggleCoach();
</script>
