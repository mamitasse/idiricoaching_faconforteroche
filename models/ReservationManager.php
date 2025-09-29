<?php
declare(strict_types=1);

namespace App\models;

use DateTime;
use PDO;
use RuntimeException;

/**
 * ReservationManager
 * ------------------
 * Accès aux données de la table `reservations` + opérations de réservation/annulation.
 *
 * Design :
 * - Méthodes qui renvoient des ENTITÉS (pattern moderne) quand c’est pertinent.
 * - Méthodes "pour la vue" qui renvoient des TABLEAUX enrichis (JOIN avec slots/users)
 *   pour faciliter l’affichage (legacy-friendly).
 * - Transactions lors des opérations critiques (reserve/cancel).
 */
final class ReservationManager extends DBManager
{
    /* =========================================================
     * Helpers privés
     * ======================================================= */

    /**
     * Convertit une ligne SQL en ReservationEntity.
     * @param array<string,mixed>|null $databaseRow
     */
    private function mapRowToEntity(?array $databaseRow): ?ReservationEntity
    {
        if (!$databaseRow) {
            return null;
        }
        return (new ReservationEntity())->fill($databaseRow);
    }

    /* =========================================================
     * Requêtes ENTITÉS
     * ======================================================= */

    /**
     * Récupère une réservation par son identifiant (entité).
     */
    public function findEntityById(int $reservationId): ?ReservationEntity
    {
        $statement = $this->db()->prepare('SELECT * FROM reservations WHERE id = :id');
        $statement->execute([':id' => $reservationId]);

        $databaseRow = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
        return $this->mapRowToEntity($databaseRow);
    }

    /* =========================================================
     * Requêtes TABLEAUX (pour les vues)
     * ======================================================= */

    /**
     * Liste toutes les réservations d’un adhérent (tableaux enrichis).
     * Retourne : id, status, paid + date, start_time, end_time + nom du coach.
     * Utile pour l’onglet “Mes réservations”.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listForAdherentView(int $adherentId): array
    {
        $sql = "SELECT r.id, r.status, r.paid,
                       s.date, s.start_time, s.end_time,
                       u.first_name AS coach_first, u.last_name AS coach_last
                FROM reservations r
                JOIN slots s ON s.id = r.slot_id
                JOIN users u ON u.id = r.coach_id
                WHERE r.adherent_id = :adherent
                ORDER BY s.date DESC, s.start_time DESC";
        $statement = $this->db()->prepare($sql);
        $statement->execute([':adherent' => $adherentId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Liste toutes les réservations d’un coach (toutes dates) (tableaux enrichis).
     * Utile pour le dashboard coach (historique + futur).
     */
    public function listForCoachView(int $coachId): array
    {
        $sql = "SELECT r.*, s.date, s.start_time, s.end_time,
                       a.first_name AS adherent_first, a.last_name AS adherent_last
                FROM reservations r
                JOIN slots s ON s.id = r.slot_id
                JOIN users a ON a.id = r.adherent_id
                WHERE r.coach_id = :coach
                ORDER BY s.date DESC, s.start_time DESC";
        $statement = $this->db()->prepare($sql);
        $statement->execute([':coach' => $coachId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Liste les réservations d’un coach pour une date précise YYYY-MM-DD (tableaux enrichis).
     */
    public function listForCoachAtDateView(int $coachId, string $dateYmd): array
    {
        $sql = "SELECT r.*, s.date, s.start_time, s.end_time,
                       a.first_name AS adherent_first, a.last_name AS adherent_last
                FROM reservations r
                JOIN slots s ON s.id = r.slot_id
                JOIN users a ON a.id = r.adherent_id
                WHERE r.coach_id = :coach AND s.`date` = :date
                ORDER BY s.start_time";
        $statement = $this->db()->prepare($sql);
        $statement->execute([':coach' => $coachId, ':date' => $dateYmd]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Liste les créneaux (slots) réservés par un adhérent chez un coach (tableaux).
     * Sert notamment au filtrage côté coach (voir “réservé par X”).
     */
    public function listReservedSlotsForAdherent(int $coachId, int $adherentId): array
    {
        $sql = "SELECT s.*
                FROM reservations r
                JOIN slots s ON s.id = r.slot_id
                WHERE r.coach_id = :coach AND r.adherent_id = :adherent AND r.status = 'confirmed'
                ORDER BY s.date, s.start_time";
        $statement = $this->db()->prepare($sql);
        $statement->execute([':coach' => $coachId, ':adherent' => $adherentId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================================================
     * Actions : réserver / annuler (transactions)
     * ======================================================= */

    /**
     * Réserve un créneau pour un adhérent :
     * 1) Verrouille le slot (SELECT ... FOR UPDATE)
     * 2) Vérifie qu’il est 'available'
     * 3) Insère une réservation 'confirmed'
     * 4) Marque le slot ‘reserved’
     * @return int Identifiant de la réservation créée
     * @throws RuntimeException si indisponible ou erreur
     */
    public function reserve(int $slotId, int $adherentId): int
    {
        $database = $this->db();

        try {
            $database->beginTransaction();

            // 1) Verrouille et charge le slot concerné
            $selectSlot = $database->prepare(
                "SELECT id, coach_id, status FROM slots WHERE id = :slotId FOR UPDATE"
            );
            $selectSlot->execute([':slotId' => $slotId]);
            $slotRow = $selectSlot->fetch(PDO::FETCH_ASSOC);

            if (!$slotRow) {
                $database->rollBack();
                throw new RuntimeException('Créneau introuvable.');
            }
            if (($slotRow['status'] ?? '') !== 'available') {
                $database->rollBack();
                throw new RuntimeException('Créneau indisponible.');
            }

            // 2) Insère la réservation
            $insertReservation = $database->prepare(
                "INSERT INTO reservations (slot_id, adherent_id, coach_id, status, paid, created_at, updated_at)
                 VALUES (:slotId, :adherentId, :coachId, 'confirmed', 0, NOW(), NOW())"
            );
            $insertReservation->execute([
                ':slotId'     => (int)$slotRow['id'],
                ':adherentId' => $adherentId,
                ':coachId'    => (int)$slotRow['coach_id'],
            ]);
            $newReservationId = (int)$database->lastInsertId();

            // 3) Marque le slot comme réservé
            $updateSlot = $database->prepare(
                "UPDATE slots SET status = 'reserved', updated_at = NOW() WHERE id = :slotId"
            );
            $updateSlot->execute([':slotId' => $slotId]);

            $database->commit();
            return $newReservationId;

        } catch (\Throwable $throwable) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $throwable;
        }
    }

    /**
     * Annule une réservation (adhérent ou coach).
     * - Si $enforce36HoursRule = true : on INTERDIT l’annulation quand
     *   le créneau commence dans ≤ 36h (règle métier).
     * - Passe la réservation en 'cancelled' et remet le slot en 'available'.
     */
    public function cancel(int $reservationId, bool $enforce36HoursRule = true): bool
    {
        $database = $this->db();

        try {
            $database->beginTransaction();

            // Récupère la réservation + info slot (verrouillage)
            $selectForCancel = $database->prepare(
                "SELECT r.id, r.status, r.slot_id, s.date, s.start_time
                 FROM reservations r
                 JOIN slots s ON s.id = r.slot_id
                 WHERE r.id = :reservationId
                 FOR UPDATE"
            );
            $selectForCancel->execute([':reservationId' => $reservationId]);
            $reservationRow = $selectForCancel->fetch(PDO::FETCH_ASSOC);

            if (!$reservationRow) {
                $database->rollBack();
                return false;
            }

            // Règle des 36h : si activée, on refuse l’annulation trop tardive
            if ($enforce36HoursRule) {
                $slotStartDateTime = new DateTime(($reservationRow['date'] ?? '').' '.($reservationRow['start_time'] ?? '00:00:00'));
                $limitDateTime     = (new DateTime())->modify('+36 hours');

                if ($slotStartDateTime <= $limitDateTime) {
                    $database->rollBack();
                    throw new RuntimeException("Annulation impossible : délai de 36h dépassé.");
                }
            }

            // Marque la réservation 'cancelled'
            $cancelReservation = $database->prepare(
                "UPDATE reservations SET status='cancelled', updated_at=NOW() WHERE id=:reservationId"
            );
            $cancelReservation->execute([':reservationId' => $reservationId]);

            // Libère le slot
            $freeSlot = $database->prepare(
                "UPDATE slots SET status='available', updated_at=NOW() WHERE id=:slotId"
            );
            $freeSlot->execute([':slotId' => (int)$reservationRow['slot_id']]);

            $database->commit();
            return true;

        } catch (\Throwable $throwable) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $throwable;
        }
    }

    /** Raccourci lisible côté contrôleur : annulation par un adhérent (règle 36h appliquée). */
    public function cancelByAdherent(int $reservationId): bool
    {
        return $this->cancel($reservationId, true);
    }

    /** Raccourci lisible : annulation par un coach (pas de contrainte de délai). */
    public function cancelByCoach(int $reservationId): bool
    {
        return $this->cancel($reservationId, false);
    }

    /* =========================================================
     * Compatibilité "legacy" (alias de nommage)
     * ======================================================= */

    /** Alias pour ancien code : forAdherent() → listForAdherentView() */
    public function forAdherent(int $adherentId): array
    {
        return $this->listForAdherentView($adherentId);
    }

    /** Alias : forCoach() → listForCoachView() */
    public function forCoach(int $coachId): array
    {
        return $this->listForCoachView($coachId);
    }

    /** Alias : forCoachAtDate() → listForCoachAtDateView() */
    public function forCoachAtDate(int $coachId, string $dateYmd): array
    {
        return $this->listForCoachAtDateView($coachId, $dateYmd);
    }

    /** Alias : reservedSlotsForAdherent() → listReservedSlotsForAdherent() */
    public function reservedSlotsForAdherent(int $coachId, int $adherentId): array
    {
        return $this->listReservedSlotsForAdherent($coachId, $adherentId);
    }
}
