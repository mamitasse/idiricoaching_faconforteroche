<?php
/**
 * Profil adhérent (vue coach)
 * Variables attendues :
 * - $adherent  : \App\models\UserEntity
 * - $upcomingReservations : array (tableaux: date, start_time, end_time, etc.)
 * - $pastReservations     : array
 */
use function App\services\escapeHtml;
?>
<section class="hero">
  <h1>Profil adhérent</h1>
  <p class="subtitle">
    Détails et historique des réservations pour
    <strong><?= escapeHtml($adherent->getFullName()) ?></strong>
  </p>
</section>

<div class="cards">
  <!-- Carte infos adhérent -->
  <article class="card">
    <h2>Informations</h2>
    <table class="table">
      <tbody>
        <tr>
          <th>Nom</th>
          <td><?= escapeHtml($adherent->getFullName()) ?></td>
        </tr>
        <tr>
          <th>Email</th>
          <td><?= escapeHtml($adherent->getEmail()) ?></td>
        </tr>
        <tr>
          <th>Téléphone</th>
          <td><?= escapeHtml((string)$adherent->getPhone()) ?></td>
        </tr>
        <tr>
          <th>Adresse</th>
          <td><?= escapeHtml((string)$adherent->getAddress()) ?></td>
        </tr>
        <tr>
          <th>Âge</th>
          <td><?= $adherent->getAge() !== null ? (int)$adherent->getAge() : '—' ?></td>
        </tr>
        <tr>
          <th>Genre</th>
          <td><?= escapeHtml((string)$adherent->getGender() ?: '—') ?></td>
        </tr>
      </tbody>
    </table>

    <div class="card-actions">
      <a class="btn" href="<?= BASE_URL ?>?action=coachDashboard">← Retour au dashboard coach</a>
    </div>
  </article>

  <!-- Réservations à venir -->
  <article class="card">
    <h2>Réservations à venir</h2>

    <?php if (empty($upcomingReservations)): ?>
      <p class="subtitle">Aucune réservation à venir.</p>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Heure</th>
            <th>Statut</th>
            <th>Payé</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($upcomingReservations as $reservation): ?>
          <tr>
            <td><?= escapeHtml(date('d/m/Y', strtotime($reservation['date']))) ?></td>
            <td>
              <?= escapeHtml(substr($reservation['start_time'], 0, 5)) ?>
              — <?= escapeHtml(substr($reservation['end_time'], 0, 5)) ?>
            </td>
            <td>
              <span class="badge"><?= escapeHtml($reservation['status']) ?></span>
            </td>
            <td>
              <span class="badge <?= ((int)($reservation['paid'] ?? 0) === 1) ? 'ok' : 'warn' ?>">
                <?= ((int)($reservation['paid'] ?? 0) === 1) ? 'Oui' : 'Non' ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </article>

  <!-- Réservations passées -->
  <article class="card">
    <h2>Réservations passées</h2>

    <?php if (empty($pastReservations)): ?>
      <p class="subtitle">Aucune réservation passée.</p>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Heure</th>
            <th>Statut</th>
            <th>Payé</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($pastReservations as $reservation): ?>
          <tr>
            <td><?= escapeHtml(date('d/m/Y', strtotime($reservation['date']))) ?></td>
            <td>
              <?= escapeHtml(substr($reservation['start_time'], 0, 5)) ?>
              — <?= escapeHtml(substr($reservation['end_time'], 0, 5)) ?>
            </td>
            <td>
              <span class="badge"><?= escapeHtml($reservation['status']) ?></span>
            </td>
            <td>
              <span class="badge <?= ((int)($reservation['paid'] ?? 0) === 1) ? 'ok' : 'warn' ?>">
                <?= ((int)($reservation['paid'] ?? 0) === 1) ? 'Oui' : 'Non' ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </article>
</div>
