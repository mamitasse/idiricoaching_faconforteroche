<?php
declare(strict_types=1);

/**
 * Vue : Formulaire de contact
 * ---------------------------
 * Variables attendues :
 * - $coachEntities : array d'App\models\UserEntity (liste des coachs pour le <select>)
 *
 * Cette vue poste vers ?action=contactPost
 * Le contrôleur (ContactController::handleContactPost) s’occupe de la validation et de l’envoi.
 */
?>
<section class="form-wrap">
  <h1>Contact</h1>
  <p class="subtitle">
    Remplis ce formulaire&nbsp;: ton message sera envoyé au coach choisi et à l’adresse
    <strong>idiricoaching56@gmail.com</strong>.
  </p>

  <form method="post" action="<?= BASE_URL ?>?action=contactPost" class="form" novalidate>
    <?php
    // CSRF : compat snake_case et camelCase
    if (function_exists('\App\services\csrf_input')) {
        echo \App\services\csrf_input();
    } elseif (function_exists('\App\services\csrfInput')) {
        echo \App\services\csrfInput();
    }
    ?>

    <!-- Identité -->
    <div class="grid-2">
      <label for="first_name">Prénom
        <input id="first_name" name="first_name" type="text" required minlength="2" maxlength="60" autocomplete="given-name">
      </label>
      <label for="last_name">Nom
        <input id="last_name" name="last_name" type="text" required minlength="2" maxlength="60" autocomplete="family-name">
      </label>
    </div>

    <!-- Coordonnées -->
    <div class="grid-2">
      <label for="email">Email
        <input id="email" name="email" type="email" required maxlength="120" autocomplete="email" placeholder="vous@exemple.fr">
      </label>
      <label for="phone">Téléphone
        <input id="phone" name="phone" type="tel" required
               placeholder="06 12 34 56 78"
               pattern="^\+?[0-9 \-\.]{9,20}$"
               title="Saisis un numéro valide (ex: 06 12 34 56 78)">
      </label>
    </div>

    <label for="address">Adresse postale
      <input id="address" name="address" type="text" required minlength="5" maxlength="255"
             placeholder="N° et rue, Code postal, Ville" autocomplete="street-address">
    </label>

    <!-- Choix du coach -->
    <label for="coach_id">Choisir un coach à contacter
      <select id="coach_id" name="coach_id" required>
        <option value="">— Sélectionner —</option>
        <?php foreach (($coachEntities ?? []) as $coachEntity): ?>
          <?php
            // On compose un libellé clair pour l’option
            $coachId    = (int)$coachEntity->getId();
            $firstName  = (string)$coachEntity->getFirstName();
            $lastName   = (string)$coachEntity->getLastName();
            $fullName   = trim($firstName . ' ' . $lastName);
          ?>
          <option value="<?= $coachId ?>">
            <?= htmlspecialchars($fullName !== '' ? $fullName : 'Coach #'.$coachId, ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <!-- Message -->
    <label for="message">Message
      <textarea id="message" name="message" rows="6" required
                minlength="10" maxlength="3000"
                placeholder="Explique ton besoin (objectifs, disponibilités, antécédents, etc.)."></textarea>
    </label>

    <div class="form-actions" style="display:flex; gap:10px; justify-content:flex-end;">
      <button class="btn" type="reset">Annuler</button>
      <button class="btn btn-primary" type="submit">Envoyer</button>
    </div>

    <p style="margin:6px 0 0; font-size:.9rem; opacity:.85;">
      En envoyant ce formulaire, tu acceptes d’être recontacté·e par le coach sélectionné.
    </p>
  </form>
</section>
