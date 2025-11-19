<?php
declare(strict_types=1);
/** @var string $title */
/** @var string $message */

use function App\services\escapeHtml as e;
?>
<section class="card error-page">
  <h1 class="error-title">404 — Page introuvable</h1>

  <p class="error-message">
    <?= e($message) ?>
  </p>

  <div class="error-actions">
    <a class="btn btn-primary" href="<?= BASE_URL ?>?action=home">Retour à l’accueil</a>
    <button class="btn" onclick="history.back()">Page précédente</button>
  </div>

  <details class="error-details">
    <summary>Besoin d’aide ?</summary>
    <ul>
      <li>Vérifiez l’orthographe de l’URL.</li>
      <li>Utilisez le menu de navigation en haut de la page.</li>
      <li>Si le problème persiste, contactez l’administrateur du site.</li>
    </ul>
  </details>
</section>
