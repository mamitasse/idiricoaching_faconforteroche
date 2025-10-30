<?php
declare(strict_types=1);

/**
 * Dashboard Coach
 * ---------------
 * Variables attendues :
 * @var string                         $coachName
 * @var string                         $todayDate
 * @var string                         $selectedDate
 * @var \App\models\SlotEntity[]       $slots          (ENTITÉS)
 * @var array<int,array<string,mixed>> $reservations   (tableaux enrichis)
 */
use function App\services\csrf_input;
use function App\services\e;
?>
<section class="card">
  <h1>Bonjour <?= e($coachName ?? '') ?></h1>
  <p>Nous sommes le <?= e($todayDate ?? '') ?></p>
</section>

<section class="card">
  <form method="get" action="" class="form" style="display:grid; grid-template-columns:1fr; gap:12px; max-width:360px;">
    <input type="hidden" name="action" value="coachDashboard">
    <label>
      Sélectionner une date
      <input type="date" name="date" value="<?= e($selectedDate ?? '') ?>" onchange="this.form.submit()">
    </label>
  </form>
</section>

<section class="card">
  <h2>Mes créneaux (<?= e((new DateTime($selectedDate ?? date('Y-m-d')))->format('d/m/Y')) ?>)</h2>

  <div class="slots-container">
    <?php if (empty($slots)): ?>
      <p>Aucun créneau pour ce jour.</p>
    <?php else: ?>
      <?php foreach ($slots as $slotEntity): ?>
        <?php
          $isReserved    = $slotEntity->isReserved();
          $isUnavailable = $slotEntity->isUnavailable();
          $cssClass      = $isReserved ? 'reserved-slot' : ($isUnavailable ? 'unavailable-slot' : 'available-slot');
          $startLabel    = $slotEntity->getStartDateTime()->format('H:i');
          $endLabel      = $slotEntity->getEndDateTime()->format('H:i');
        ?>
        <div class="slot <?= $cssClass ?>">
          <div class="slot-time"><?= e($startLabel.' - '.$endLabel) ?></div>

          <div class="slot-actions">
            <?php if (!$isReserved && !$isUnavailable): ?>
              <!-- Bloquer -->
              <form class="inline" method="post" action="<?= BASE_URL ?>?action=slotBlock">
                <?= csrf_input() ?>
                <input type="hidden" name="slot_id" value="<?= (int)$slotEntity->getId() ?>">
                <input type="hidden" name="date" value="<?= e($selectedDate ?? '') ?>">
                <button class="btn" type="submit">Bloquer</button>
              </form>
            <?php endif; ?>

            <?php if ($isUnavailable): ?>
              <!-- Débloquer -->
              <form class="inline" method="post" action="<?= BASE_URL ?>?action=slotUnblock">
                <?= csrf_input() ?>
                <input type="hidden" name="slot_id" value="<?= (int)$slotEntity->getId() ?>">
                <input type="hidden" name="date" value="<?= e($selectedDate ?? '') ?>">
                <button class="btn" type="submit">Libérer</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>



<section class="card">
  <h2>Mes adhérents</h2>

  <?php if (empty($adherents)): ?>
    <p class="subtitle">Aucun adhérent rattaché pour le moment.</p>
  <?php else: ?>
    <label style="display:block; max-width:520px;">
      Choisir un adhérent
      <select
        name="id"
        onchange="if(this.value){ window.location='<?= BASE_URL ?>?action=coachAdherentProfile&id='+this.value; }"
      >
        <option value="">— Sélectionner —</option>
        <?php foreach ($adherents as $adherentEntity): ?>
          <option value="<?= (int)$adherentEntity->getId() ?>">
            <?= htmlspecialchars($adherentEntity->getLastName().' '.$adherentEntity->getFirstName(), ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
  <?php endif; ?>
</section>



<section class="card">
  <h2>Réservations du jour</h2>
  <?php if (empty($reservations)): ?>
    <p>Aucune réservation pour ce jour.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Heure</th><th>Adhérent</th><th>Statut</th><th>Payé</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($reservations as $reservationRow): ?>
          <?php
            $startLabel = (new DateTime($reservationRow['start_time']))->format('H:i');
            $endLabel   = (new DateTime($reservationRow['end_time']))->format('H:i');
            $adherent   = trim(($reservationRow['adherent_first'] ?? '').' '.($reservationRow['adherent_last'] ?? ''));
          ?>
          <tr>
            <td><?= e($startLabel.' - '.$endLabel) ?></td>
            <td><?= e($adherent) ?></td>
            <td><?= e($reservationRow['status'] ?? '') ?></td>
            <td><?= (int)($reservationRow['paid'] ?? 0) === 1 ? 'Oui' : 'Non' ?></td>
            <td>
              <?php if (($reservationRow['status'] ?? '') !== 'cancelled'): ?>
                <form method="post" action="<?= BASE_URL ?>?action=reservationCancelByCoach" class="inline"
                      onsubmit="return confirm('Confirmer l’annulation ?');">
                  <?= csrf_input() ?>
                  <input type="hidden" name="reservation_id" value="<?= (int)$reservationRow['id'] ?>">
                  <button class="btn">Annuler</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
