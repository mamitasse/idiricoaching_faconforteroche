<?php
declare(strict_types=1);

namespace App\models;

use DateTime;
use PDO;

final class SlotManager extends DBManager
{
    /* ---------------- Helpers ENTITÉS ---------------- */

    private function toEntity(?array $row): ?SlotEntity
    {
        if (!$row) return null;
        return (new SlotEntity())->fill($row);
    }

    /* ---------------- Version ENTITÉS ---------------- */

    public function findEntityById(int $id): ?SlotEntity
    {
        $sql = "SELECT * FROM slots WHERE id = :id";
        $st  = $this->db()->prepare($sql);
        $st->execute([':id' => $id]);
        return $this->toEntity($st->fetch(PDO::FETCH_ASSOC) ?: null);
    }

    /** Liste d’entités pour un coach/date */
    public function listEntitiesForCoachDate(int $coachId, string $date): array
    {
        $sql = "SELECT * FROM slots
                WHERE coach_id = :c AND `date` = :d
                ORDER BY start_time";
        $st  = $this->db()->prepare($sql);
        $st->execute([':c' => $coachId, ':d' => $date]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r) => $this->toEntity($r), $rows);
    }

    /* -------------- Version TABLEAU (legacy) -------------- */

    /**
     * ***NOUVELLE*** méthode utilisée par DashboardController.
     * Renvoie les créneaux (tous statuts) au format tableau pour un coach/date.
     */
    public function listForCoachDate(int $coachId, string $date): array
    {
        $sql = "SELECT * FROM slots
                WHERE coach_id = :c AND `date` = :d
                ORDER BY start_time";
        $st  = $this->db()->prepare($sql);
        $st->execute([':c' => $coachId, ':d' => $date]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Alias conservé si d’anciens fichiers appellent encore cette méthode */
    public function daySlotsForCoach(int $coachId, string $date): array
    {
        return $this->listForCoachDate($coachId, $date);
    }

    /** Créneaux *disponibles* uniquement (format tableau) */
    public function availableByDayForCoach(int $coachId, string $date): array
    {
        $sql = "SELECT id, coach_id, `date`, start_time, end_time, status
                FROM slots
                WHERE coach_id = :c AND `date` = :d AND status = 'available'
                ORDER BY start_time";
        $st  = $this->db()->prepare($sql);
        $st->execute([':c' => $coachId, ':d' => $date]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ----------------- Génération des grilles ----------------- */

    /**
     * Assure la grille d'1 jour (08→20, pas de 1h).
     * On utilise INSERT IGNORE : si l’index unique (coach_id, date, start_time) est présent,
     * aucun doublon n’est créé et aucune erreur n’est levée.
     */
    public function ensureDailyGrid(int $coachId, string $date, int $hStart = 8, int $hEnd = 20): void
    {
        $pdo = $this->db();
        $sql = "INSERT IGNORE INTO slots (coach_id, `date`, start_time, end_time, status)
                VALUES (:c, :d, :s, :e, 'available')";
        $st = $pdo->prepare($sql);

        for ($h = $hStart; $h < $hEnd; $h++) {
            $s = sprintf('%02d:00:00', $h);
            $e = sprintf('%02d:00:00', $h + 1);
            $st->execute([':c' => $coachId, ':d' => $date, ':s' => $s, ':e' => $e]);
        }
    }

    /** Assure la grille d’1 mois (appelle ensureDailyGrid pour chaque jour) */
    public function ensureMonthGrid(int $coachId, int $year, int $month, int $hStart = 8, int $hEnd = 20): void
    {
        $first = new DateTime(sprintf('%04d-%02d-01', $year, $month));
        $d = clone $first;
        while ((int)$d->format('m') === $month) {
            $this->ensureDailyGrid($coachId, $d->format('Y-m-d'), $hStart, $hEnd);
            $d->modify('+1 day');
        }
    }

    /* ----------------- Changement de statut ----------------- */

    /** Bloquer un créneau (le coach rend indisponible) */
    public function block(int $slotId): bool
    {
        $sql = "UPDATE slots SET status='unavailable' WHERE id = :id AND status <> 'reserved'";
        $ok = $this->db()->prepare($sql)->execute([':id' => $slotId]);
        return $ok;
    }

    /** Libérer un créneau (repasse en available) */
    public function free(int $slotId): bool
    {
        $sql = "UPDATE slots SET status='available' WHERE id = :id AND status <> 'reserved'";
        $ok = $this->db()->prepare($sql)->execute([':id' => $slotId]);
        return $ok;
    }

    /**
     * Marquer réservé (utilisé juste après l’insertion de réservation coté ReservationManager)
     * On ne réserve que si le créneau était 'available'.
     */
    public function markReserved(int $slotId): bool
    {
        $sql = "UPDATE slots SET status='reserved' WHERE id = :id AND status = 'available'";
        $ok  = $this->db()->prepare($sql)->execute([':id' => $slotId]);
        return $ok;
    }
}

