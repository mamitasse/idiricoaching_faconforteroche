<?php
/** @var string $userName */
/** @var string $coachName */
/** @var string $todayDate */
/** @var string $selectedDate */
/** @var App\models\SlotEntity[] $slots */
/** @var array $reservations */

use function App\services\e;
use function App\services\csrf_input;

function timeHM(string $sqlTime): string {
  // $sqlTime = 'HH:MM:SS'
  return substr($sqlTime, 0, 5);
}
function dmy(string $ymd): string {
  $p = explode('-', $ymd);
  return (count($p)===3) ? ($p[2].'/'.$p[1].'/'.$p[0]) : $ymd;
}
?>
<section class="card">
  <h1>Bonjour <?= e($userName) ?></h1>
  <p>Nous sommes le <?= e($todayDate) ?> — Ton coach : <strong><?= e($coachName) ?></strong></p>
</section>

<section class="card">
  <form method="get" action="" class="form" style="display:grid; gap:12px; max-width:360px;">
    <input type="hidden" name="action" value="adherentDashboard">
    <label>
      Sélectionner une date
      <input type="date" name="date" value="<?= e($selectedDate) ?>" onchange="this.form.submit()">
    </label>
  </form>
</section>

<section class="card">
  <h2>Créneaux disponibles — <?= e(dmy($selectedDate)) ?> (coach <?= e($coachName) ?>)</h2>

  <div class="slots-container">
    <?php if (empty($slots)): ?>
      <p>Aucun créneau disponible pour cette journée.</p>
    <?php else: ?>
      <?php foreach ($slots as $s): ?>
        <div class="slot available-slot">
          <div class="slot-time">
            <?= e(timeHM($s->getStartTime())) ?> - <?= e(timeHM($s->getEndTime())) ?>
          </div>
          <div class="slot-actions">
            <form class="inline" method="post" action="<?= BASE_URL ?>?action=creneauReserve">
              <?= csrf_input() ?>
              <input type="hidden" name="slot_id" value="<?= (int)$s->getId() ?>">
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
      <thead><tr><th>Date</th><th>Heure</th><th>Coach</th><th>Statut</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($reservations as $r): ?>
        <?php
          $date  = dmy((string)($r['date'] ?? ''));
          $heure = substr((string)($r['start_time'] ?? ''),0,5).' - '.substr((string)($r['end_time'] ?? ''),0,5);
          $canCancel = false;
          if (!empty($r['date']) && !empty($r['start_time'])) {
            $start = new DateTime($r['date'].' '.$r['start_time']);
            $canCancel = ($start > (new DateTime())->modify('+36 hours')) && (($r['status'] ?? '') !== 'cancelled');
          }
        ?>
        <tr>
          <td><?= e($date) ?></td>
          <td><?= e($heure) ?></td>
          <td><?= e(($r['coach_first'] ?? '').' '.($r['coach_last'] ?? '')) ?></td>
          <td><?= e($r['status'] ?? '') ?><?= ((int)($r['paid'] ?? 0)===1?' (payé)':'') ?></td>
          <td>
            <?php if ($canCancel): ?>
              <form method="post" action="<?= BASE_URL ?>?action=reservationCancel" class="inline"
                    onsubmit="return confirm('Confirmer l’annulation ?');">
                <?= csrf_input() ?>
                <input type="hidden" name="reservation_id" value="<?= (int)($r['id'] ?? 0) ?>">
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
