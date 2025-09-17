<?php
declare(strict_types=1);

namespace App\models;

/**
 * Représentation métier d’une réservation (table reservations).
 */
final class ReservationEntity extends AbstractEntity
{
    private ?int $id          = null;
    private int $slot_id      = 0;
    private int $adherent_id  = 0;
    private int $coach_id     = 0;
    private string $status    = 'confirmed';   // confirmed|cancelled|pending
    private int $paid         = 0;             // 0/1
    private ?string $created_at = null;
    private ?string $updated_at = null;

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getSlotId(): int { return $this->slot_id; }
    public function getAdherentId(): int { return $this->adherent_id; }
    public function getCoachId(): int { return $this->coach_id; }
    public function getStatus(): string { return $this->status; }
    public function isPaid(): bool { return $this->paid === 1; }
    public function getCreatedAt(): ?string { return $this->created_at; }
    public function getUpdatedAt(): ?string { return $this->updated_at; }

    // Setters
    public function setId($v): void { $this->id = ($v === null ? null : (int)$v); }
    public function setSlotId($v): void { $this->slot_id = (int)$v; }
    public function setAdherentId($v): void { $this->adherent_id = (int)$v; }
    public function setCoachId($v): void { $this->coach_id = (int)$v; }
    public function setStatus($v): void { $this->status = (string)$v; }
    public function setPaid($v): void { $this->paid = (int)$v; }
    public function setCreatedAt($v): void { $this->created_at = $v !== null ? (string)$v : null; }
    public function setUpdatedAt($v): void { $this->updated_at = $v !== null ? (string)$v : null; }

    // Helpers confort
    public function isConfirmed(): bool { return $this->status === 'confirmed'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
}
