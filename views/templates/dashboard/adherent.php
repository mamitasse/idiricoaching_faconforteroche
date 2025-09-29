<?php
declare(strict_types=1);

/**
 * Dashboard Adhérent
 * ------------------
 * Variables attendues :
 * @var string                         $userName       Nom complet de l’adhérent (ex: “Jean Dupont”)
 * @var string                         $coachName      Nom complet du coach
 * @var string                         $todayDate      Date du jour formatée (ex: 16/09/2025)
 * @var string                         $selectedDate   Date sélectionnée au format SQL YYYY-MM-DD
 * @var \App\models\SlotEntity[]       $slots          Créneaux ENTITÉS (filtrés en amont pour être disponibles)
 * @var array<int,array<string,mixed>> $reservations   Réservations (tableaux enrichis via JOIN)
 */

use function App\services\csrf_input;
use function App\services\e;

function timeHmFromSql(string $sqlDateTime): string {
    return (new DateTime($sqlDateTime))->format('H:i');
}
function dateFrFromYmd(string $ymd): string {
    return (new DateTime($ymd))->format('d/m/Y');
}
?>
<section class="card">
  <h1>Bonjour <?= e($userName ?? '') ?></h1>
  <p>Nous sommes le <?= e($todayDate ?? '') ?> — Ton coach : <strong><?= e($coachName ?? '—') ?></strong></p>
</section>

<section class="card">
  <form method="get" action="" class="form" style="display:grid; grid-template-columns:1fr; gap:12px; max-width:360px;">
    <input type="hidden" name="action" value="adherentDashboard">
    <label>
      Sélectionner une date
      <input type="date" name="date" value="<?= e($selectedDate ?? '') ?>" onchange="this.form.submit()">
    </label>
  </form>
</section>

<section class="card">
  <h2>Créneaux disponibles — <?= e(dateFrFromYmd($selectedDate ?? date('Y-m-d'))) ?> (coach <?= e($coachName ?? '—') ?>)</h2>

  <div class="slots-container">
    <?php if (empty($slots)): ?>
      <p>Aucun créneau disponible pour cette journée.</p>
    <?php else: ?>
      <?php foreach ($slots as $slotEntity): ?>
        <div class="slot available-slot">
          <div class="slot-time">
            <?= e($slotEntity->getStartDateTime()->format('H:i')) ?>
            -
            <?= e($slotEntity->getEndDateTime()->format('H:i')) ?>
          </div>
          <div class="slot-actions">
            <form class="inline" method="post" action="<?= BASE_URL ?>?action=creneauReserve">
              <?= csrf_input() ?>
              <input type="hidden" name="slot_id" value="<?= (int)$slotEntity->getId() ?>">
              <button class="btn btn-primary" type="submit">Réserver</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<section class="card">
  <h2>Mes réservations</h2>

  <?php if (empty($reservations)): ?>
    <p>Aucune réservation.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Date</th><th>Heure</th><th>Coach</th><th>Statut</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($reservations as $reservationRow): ?>
        <?php
          $startDate = new DateTime($reservationRow['start_time']);
          $dateFr    = $startDate->format('d/m/Y');
          $heureFr   = $startDate->format('H:i').' - '.(new DateTime($reservationRow['end_time']))->format('H:i');
          $canCancel = ($startDate > (new DateTime())->modify('+36 hours')) && (($reservationRow['status'] ?? '') !== 'cancelled');
        ?>
        <tr>
          <td><?= e($dateFr) ?></td>
          <td><?= e($heureFr) ?></td>
          <td><?= e(($reservationRow['coach_first'] ?? '').' '.($reservationRow['coach_last'] ?? '')) ?></td>
          <td><?= e($reservationRow['status'] ?? '') ?><?= ((int)($reservationRow['paid'] ?? 0) === 1 ? ' (payé)' : '') ?></td>
          <td>
            <?php if ($canCancel): ?>
              <form method="post" action="<?= BASE_URL ?>?action=reservationCancel" class="inline"
                    onsubmit="return confirm('Confirmer l’annulation ?');">
                <?= csrf_input() ?>
                <input type="hidden" name="reservation_id" value="<?= (int)$reservationRow['id'] ?>">
                <button class="btn" type="submit">Annuler</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
