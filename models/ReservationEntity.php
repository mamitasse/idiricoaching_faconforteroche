<?php
declare(strict_types=1);

namespace App\models;

/**
 * ReservationEntity
 * -----------------
 * Représentation métier d’une ligne de la table `reservations`.
 * On stocke les valeurs telles qu’elles existent en base (id, slot_id, etc.)
 * et on expose des getters/setters + quelques helpers de confort.
 *
 * Remarque :
 * - Les dates/heures sont souvent retournées par le Manager via un JOIN avec `slots`.
 *   L’entité reste volontairement focalisée sur les colonnes de `reservations`.
 */
final class ReservationEntity extends AbstractEntity
{
    /** Identifiant de la réservation (PK) */
    private ?int $id = null;

    /** Clé étrangère vers le créneau réservé */
    private int $slot_id = 0;

    /** Clé étrangère vers l’adhérent (utilisateur) qui réserve */
    private int $adherent_id = 0;

    /** Clé étrangère vers le coach (utilisateur) qui “possède” le créneau */
    private int $coach_id = 0;

    /** Statut de la réservation : confirmed | cancelled | pending */
    private string $status = 'confirmed';

    /** Paiement effectué : 0 ou 1 */
    private int $paid = 0;

    /** Timestamps (optionnels selon ton schéma) */
    private ?string $created_at = null;
    private ?string $updated_at = null;

    // ---------------- Getters (lecture) ----------------
    public function getId(): ?int                 { return $this->id; }
    public function getSlotId(): int              { return $this->slot_id; }
    public function getAdherentId(): int          { return $this->adherent_id; }
    public function getCoachId(): int             { return $this->coach_id; }
    public function getStatus(): string           { return $this->status; }
    public function isPaid(): bool                { return $this->paid === 1; }
    public function getCreatedAt(): ?string       { return $this->created_at; }
    public function getUpdatedAt(): ?string       { return $this->updated_at; }

    // ---------------- Setters (écriture/hydratation) ----------------
    public function setId($value): void           { $this->id = ($value === null ? null : (int)$value); }
    public function setSlotId($value): void       { $this->slot_id = (int)$value; }
    public function setAdherentId($value): void   { $this->adherent_id = (int)$value; }
    public function setCoachId($value): void      { $this->coach_id = (int)$value; }
    public function setStatus($value): void       { $this->status = (string)$value; }
    public function setPaid($value): void         { $this->paid = (int)$value; }
    public function setCreatedAt($value): void    { $this->created_at = $value !== null ? (string)$value : null; }
    public function setUpdatedAt($value): void    { $this->updated_at = $value !== null ? (string)$value : null; }

    // ---------------- Helpers de confort ----------------
    public function isConfirmed(): bool           { return $this->status === 'confirmed'; }
    public function isCancelled(): bool           { return $this->status === 'cancelled'; }
}
