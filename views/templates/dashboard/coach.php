<?php
/** @var string $coachName */
/** @var string $todayDate */
/** @var string $selectedDate */
/** @var App\models\SlotEntity[] $slots */
/** @var array $reservations */
/** @var array $adherents */

use function App\services\e;
use function App\services\csrf_input;

function timeHM(string $sqlTime): string { return substr($sqlTime,0,5); }
function dmy(string $ymd): string { $p=explode('-',$ymd); return (count($p)===3)?($p[2].'/'.$p[1].'/'.$p[0]):$ymd; }
?>
<section class="card">
  <h1>Bonjour <?= e($coachName) ?></h1>
  <p>Nous sommes le <?= e($todayDate) ?></p>
</section>

<section class="card">
  <form method="get" action="" class="form" style="display:grid; gap:12px; max-width:360px;">
    <input type="hidden" name="action" value="coachDashboard">
    <label>
      Sélectionner une date
      <input type="date" name="date" value="<?= e($selectedDate) ?>" onchange="this.form.submit()">
    </label>
  </form>
</section>

<section class="card">
  <h2>Créneaux du <?= e(dmy($selectedDate)) ?></h2>

  <div class="slots-container">
    <?php if (empty($slots)): ?>
      <p>Aucun créneau pour cette journée (une grille 08→20 a été générée automatiquement).</p>
    <?php else: foreach ($slots as $s): ?>
      <?php
        $cls = $s->isReserved() ? 'reserved-slot' : ($s->isUnavailable() ? 'unavailable-slot' : 'available-slot');
      ?>
      <div class="slot <?= e($cls) ?>">
        <div class="slot-time"><?= e(timeHM($s->getStartTime())) ?> - <?= e(timeHM($s->getEndTime())) ?></div>
        <div class="slot-actions">
          <?php if ($s->isAvailable()): ?>
            <form class="inline" method="post" action="<?= BASE_URL ?>?action=creneauBlock">
              <?= csrf_input() ?>
              <input type="hidden" name="slot_id" value="<?= (int)$s->getId() ?>">
              <input type="hidden" name="date" value="<?= e($selectedDate) ?>">
              <button class="btn" type="submit">Indisponible</button>
            </form>
          <?php elseif ($s->isUnavailable()): ?>
            <form class="inline" method="post" action="<?= BASE_URL ?>?action=creneauUnblock">
              <?= csrf_input() ?>
              <input type="hidden" name="slot_id" value="<?= (int)$s->getId() ?>">
              <input type="hidden" name="date" value="<?= e($selectedDate) ?>">
              <button class="btn" type="submit">Disponible</button>
            </form>
          <?php else: ?>
            <span>Réservé</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</section>

<section class="card">
  <h2>Réservations du jour</h2>
  <?php if (empty($reservations)): ?>
    <p>Aucune réservation pour cette date.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Heure</th><th>Adhérent</th><th>Statut</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($reservations as $r): ?>
          <?php
            $heure = substr((string)($r['start_time'] ?? ''),0,5).' - '.substr((string)($r['end_time'] ?? ''),0,5);
            $isCancelled = (($r['status'] ?? '') === 'cancelled');
          ?>
          <tr>
            <td><?= e($heure) ?></td>
            <td><?= e(($r['adh_first'] ?? '').' '.($r['adh_last'] ?? '')) ?></td>
            <td><?= e($r['status'] ?? '') ?><?= ((int)($r['paid'] ?? 0)===1?' (payé)':'') ?></td>
            <td>
              <?php if (!$isCancelled): ?>
                <form method="post" action="<?= BASE_URL ?>?action=reservationCancelByCoach" class="inline"
                      onsubmit="return confirm('Annuler cette réservation ?');">
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
