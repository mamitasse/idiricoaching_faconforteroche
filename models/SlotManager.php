<?php
declare(strict_types=1);

namespace App\models;

use DateTime;
use PDO;

/**
 * SlotManager
 * -----------
 * Accès aux créneaux de la table `slots` et génération de grilles (jour/mois).
 * - Méthodes ENTITÉS par défaut (listEntitiesForCoachDate, findEntityById, …)
 * - Méthodes TABLEAUX pour compatibilité avec les vues existantes (legacy).
 * - Méthodes de mise à jour de statut (block/free/markReserved).
 *
 * Hypothèse recommandée :
 * - Index unique : (coach_id, date, start_time) afin d’utiliser INSERT IGNORE
 *   lors de la génération de grilles sans créer de doublons.
 */
final class SlotManager extends DBManager
{
    /* =========================================================
     * Helpers privés
     * ======================================================= */

    /**
     * Convertit une ligne SQL en SlotEntity.
     * @param array<string,mixed>|null $databaseRow
     */
    private function mapRowToEntity(?array $databaseRow): ?SlotEntity
    {
        if (!$databaseRow) {
            return null;
        }
        return (new SlotEntity())->fill($databaseRow);
    }

    /* =========================================================
     * Requêtes ENTITÉS
     * ======================================================= */

    /** Charge un créneau par son ID (entité). */
    public function findEntityById(int $slotId): ?SlotEntity
    {
        $statement = $this->db()->prepare("SELECT * FROM slots WHERE id = :id");
        $statement->execute([':id' => $slotId]);

        $databaseRow = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
        return $this->mapRowToEntity($databaseRow);
    }

    /**
     * Liste des créneaux (ENTITÉS) d’un coach pour une date précise (YYYY-MM-DD).
     * @return SlotEntity[]
     */
    public function listEntitiesForCoachDate(int $coachId, string $dateYmd): array
    {
        $statement = $this->db()->prepare(
            "SELECT * FROM slots
             WHERE coach_id = :coach AND `date` = :date
             ORDER BY start_time"
        );
        $statement->execute([':coach' => $coachId, ':date' => $dateYmd]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $row): SlotEntity => $this->mapRowToEntity($row), $rows);
    }

    /* =========================================================
     * Requêtes TABLEAUX (legacy / vues)
     * ======================================================= */

    /**
     * Liste des créneaux *disponibles* pour une date/coach (tableaux).
     * Utile pour tes anciennes vues “adherent” qui attendent un array.
     */
    public function availableByDayForCoach(int $coachId, string $dateYmd): array
    {
        $sql = "SELECT id, coach_id, `date`, start_time, end_time, status
                FROM slots
                WHERE coach_id = :coach AND `date` = :date AND status = 'available'
                ORDER BY start_time";
        $statement = $this->db()->prepare($sql);
        $statement->execute([':coach' => $coachId, ':date' => $dateYmd]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Liste des créneaux (tous statuts) pour une date/coach (tableaux).
     * Alias utile pour compatibilité.
     */
    public function listForCoachDate(int $coachId, string $dateYmd): array
    {
        $sql = "SELECT * FROM slots
                WHERE coach_id = :coach AND `date` = :date
                ORDER BY start_time";
        $statement = $this->db()->prepare($sql);
        $statement->execute([':coach' => $coachId, ':date' => $dateYmd]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Alias historique : daySlotsForCoach() → listForCoachDate() */
    public function daySlotsForCoach(int $coachId, string $dateYmd): array
    {
        return $this->listForCoachDate($coachId, $dateYmd);
    }

    /* =========================================================
     * Génération de grilles (jour / mois)
     * ======================================================= */

    /**
     * Assure la grille d’1 jour pour un coach (par défaut 08:00 → 20:00, pas de 1h).
     * Utilise INSERT IGNORE si tu as un index unique (coach_id, date, start_time).
     */
    public function ensureDailyGrid(int $coachId, string $dateYmd, int $hourStart = 8, int $hourEnd = 20): void
    {
        $database = $this->db();
        $insert   = $database->prepare(
            "INSERT IGNORE INTO slots (coach_id, `date`, start_time, end_time, status)
             VALUES (:coach, :date, :start, :end, 'available')"
        );

        for ($hour = $hourStart; $hour < $hourEnd; $hour++) {
            $start = sprintf('%02d:00:00', $hour);
            $end   = sprintf('%02d:00:00', $hour + 1);

            $insert->execute([
                ':coach' => $coachId,
                ':date'  => $dateYmd,
                ':start' => $start,
                ':end'   => $end,
            ]);
        }
    }

    /**
     * Assure la grille d’1 mois complet : appelle ensureDailyGrid() pour chaque jour du mois.
     */
    public function ensureMonthGrid(int $coachId, int $year, int $month, int $hourStart = 8, int $hourEnd = 20): void
    {
        $firstDay = new DateTime(sprintf('%04d-%02d-01', $year, $month));
        $day = clone $firstDay;

        while ((int)$day->format('m') === $month) {
            $this->ensureDailyGrid($coachId, $day->format('Y-m-d'), $hourStart, $hourEnd);
            $day->modify('+1 day');
        }
    }

    /* =========================================================
     * Mise à jour des statuts
     * ======================================================= */

    /** Le coach rend un créneau indisponible (sauf s’il est déjà réservé). */
    public function block(int $slotId): bool
    {
        $statement = $this->db()->prepare(
            "UPDATE slots SET status='unavailable'
             WHERE id = :slotId AND status <> 'reserved'"
        );
        return $statement->execute([':slotId' => $slotId]);
    }

    /** Le coach libère un créneau (repasse en available) s’il n’est pas réservé. */
    public function free(int $slotId): bool
    {
        $statement = $this->db()->prepare(
            "UPDATE slots SET status='available'
             WHERE id = :slotId AND status <> 'reserved'"
        );
        return $statement->execute([':slotId' => $slotId]);
    }

    /**
     * Marque “réservé” (utilisé juste après l’insertion d’une réservation côté ReservationManager).
     * Sécurisé : ne bascule que si le statut est encore 'available'.
     */
    public function markReserved(int $slotId): bool
    {
        $statement = $this->db()->prepare(
            "UPDATE slots SET status='reserved'
             WHERE id = :slotId AND status = 'available'"
        );
        return $statement->execute([':slotId' => $slotId]);
    }
}
