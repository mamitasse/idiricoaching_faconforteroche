<?php
declare(strict_types=1);

namespace App\models;

use DateTime;
use PDO;
use RuntimeException;

final class ReservationManager extends DBManager
{
    /* =======================
       Helpers ENTITÉS
       ======================= */
    private function toEntity(?array $row): ?ReservationEntity
    {
        if (!$row) return null;
        return (new ReservationEntity())->fill($row);
    }

    /* =======================
       Version ENTITÉS
       ======================= */

    public function findEntityById(int $id): ?ReservationEntity
    {
        $st = $this->db()->prepare("SELECT * FROM reservations WHERE id = :id");
        $st->execute([':id'=>$id]);
        return $this->toEntity($st->fetch(PDO::FETCH_ASSOC) ?: null);
    }

    /* =======================
       Réservations (legacy/tableaux + quelques entités)
       ======================= */

    /** Réservations d’un adhérent avec infos du coach et des heures (tableau pour la vue actuelle). */
    public function forAdherent(int $adherentId): array
    {
        $sql = "SELECT r.id, r.status, r.paid,
                       s.date, s.start_time, s.end_time,
                       u.first_name AS coach_first, u.last_name AS coach_last
                FROM reservations r
                JOIN slots s   ON s.id = r.slot_id
                JOIN users u   ON u.id = r.coach_id
                WHERE r.adherent_id = :a
                ORDER BY s.date DESC, s.start_time DESC";
        $st = $this->db()->prepare($sql);
        $st->execute([':a'=>$adherentId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Réservations d’un coach (toutes dates). */
    public function forCoach(int $coachId): array
    {
        $sql = "SELECT r.*, s.date, s.start_time, s.end_time,
                       ua.first_name AS adh_first, ua.last_name AS adh_last
                FROM reservations r
                JOIN slots s ON s.id = r.slot_id
                JOIN users ua ON ua.id = r.adherent_id
                WHERE r.coach_id = :c
                ORDER BY s.date DESC, s.start_time DESC";
        $st = $this->db()->prepare($sql);
        $st->execute([':c'=>$coachId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Réservations d’un coach pour une date donnée (YYYY-MM-DD). */
    public function forCoachAtDate(int $coachId, string $date): array
    {
        $sql = "SELECT r.*, s.date, s.start_time, s.end_time,
                       ua.first_name AS adh_first, ua.last_name AS adh_last
                FROM reservations r
                JOIN slots s ON s.id = r.slot_id
                JOIN users ua ON ua.id = r.adherent_id
                WHERE r.coach_id = :c AND s.`date` = :d
                ORDER BY s.start_time";
        $st = $this->db()->prepare($sql);
        $st->execute([':c'=>$coachId, ':d'=>$date]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Créneaux (slots) réservés par un adhérent chez un coach. */
    public function reservedSlotsForAdherent(int $coachId, int $adherentId): array
    {
        $sql = "SELECT s.*
                FROM reservations r
                JOIN slots s ON s.id = r.slot_id
                WHERE r.coach_id = :c AND r.adherent_id = :a AND r.status = 'confirmed'
                ORDER BY s.date, s.start_time";
        $st = $this->db()->prepare($sql);
        $st->execute([':c'=>$coachId, ':a'=>$adherentId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =======================
       Actions : réserver / annuler
       ======================= */

    /**
     * Réserve un créneau pour un adhérent :
     * - vérifie que le slot est 'available'
     * - crée la réservation
     * - passe le slot en 'reserved'
     * Transactionnelle.
     * Retourne l’ID de réservation créée.
     */
    public function reserve(int $slotId, int $adherentId): int
    {
        $pdo = $this->db();

        // Récupère le slot
        $st = $pdo->prepare("SELECT id, coach_id, status FROM slots WHERE id = :id FOR UPDATE");
        $pdo->beginTransaction();
        $st->execute([':id'=>$slotId]);
        $slot = $st->fetch(PDO::FETCH_ASSOC);

        if (!$slot) {
            $pdo->rollBack();
            throw new RuntimeException("Créneau introuvable.");
        }
        if (($slot['status'] ?? '') !== 'available') {
            $pdo->rollBack();
            throw new RuntimeException("Créneau indisponible.");
        }

        // Insère la réservation
        $ins = $pdo->prepare(
            "INSERT INTO reservations (slot_id, adherent_id, coach_id, status, paid, created_at, updated_at)
             VALUES (:sid, :aid, :cid, 'confirmed', 0, NOW(), NOW())"
        );
        $ins->execute([
            ':sid' => (int)$slot['id'],
            ':aid' => $adherentId,
            ':cid' => (int)$slot['coach_id'],
        ]);
        $resId = (int)$pdo->lastInsertId();

        // Marque le slot comme réservé
        $upd = $pdo->prepare("UPDATE slots SET status = 'reserved', updated_at = NOW() WHERE id = :sid");
        $upd->execute([':sid'=>$slotId]);

        $pdo->commit();
        return $resId;
    }

    /**
     * Annule une réservation (par l’adhérent ou le coach).
     * Si $enforce36h = true, on interdit l’annulation > 36h avant le début du créneau.
     * Remet le slot en 'available'.
     */
    public function cancel(int $reservationId, bool $enforce36h = true): bool
    {
        $pdo = $this->db();
        $pdo->beginTransaction();

        // Récupère la réservation + les infos du slot
        $st = $pdo->prepare(
            "SELECT r.id, r.status, r.slot_id,
                    s.date, s.start_time
             FROM reservations r
             JOIN slots s ON s.id = r.slot_id
             WHERE r.id = :id
             FOR UPDATE"
        );
        $st->execute([':id'=>$reservationId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) { $pdo->rollBack(); return false; }

        // Règle des 36h
        if ($enforce36h) {
            $start = new DateTime(($r['date'] ?? '').' '.($r['start_time'] ?? '00:00:00'));
            $limit = (new DateTime())->modify('+36 hours');
            if ($start <= $limit) {
                $pdo->rollBack();
                throw new RuntimeException("Annulation impossible : délai de 36h dépassé.");
            }
        }

        // Annule la réservation + libère le slot
        $pdo->prepare("UPDATE reservations SET status='cancelled', updated_at=NOW() WHERE id=:id")
            ->execute([':id'=>$reservationId]);

        $pdo->prepare("UPDATE slots SET status='available', updated_at=NOW() WHERE id=:sid")
            ->execute([':sid'=>(int)$r['slot_id']]);

        $pdo->commit();
        return true;
    }

    /** Alias lisibles */
    public function cancelByAdherent(int $reservationId): bool { return $this->cancel($reservationId, true); }
    public function cancelByCoach(int $reservationId): bool     { return $this->cancel($reservationId, false); }
}
