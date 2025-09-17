<?php
declare(strict_types=1);

namespace App\models;

/**
 * Représentation métier d’un créneau (table slots).
 * On garde les champs date / time en string (YYYY-MM-DD / HH:MM:SS)
 * pour rester 1:1 avec la BDD, et on expose des helpers DateTime.
 */
final class SlotEntity extends AbstractEntity
{
    private ?int $id         = null;
    private int $coach_id    = 0;
    private string $date     = '';        // YYYY-MM-DD
    private string $start_time = '';      // HH:MM:SS
    private string $end_time   = '';      // HH:MM:SS
    private string $status     = 'available'; // available|reserved|unavailable
    private ?string $created_at = null;
    private ?string $updated_at = null;

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getCoachId(): int { return $this->coach_id; }
    public function getDate(): string { return $this->date; }
    public function getStartTime(): string { return $this->start_time; }
    public function getEndTime(): string { return $this->end_time; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedAt(): ?string { return $this->created_at; }
    public function getUpdatedAt(): ?string { return $this->updated_at; }

    // Setters (hydratation via AbstractEntity::fill)
    public function setId($v): void { $this->id = ($v === null ? null : (int)$v); }
    public function setCoachId($v): void { $this->coach_id = (int)$v; }
    public function setDate($v): void { $this->date = (string)$v; }
    public function setStartTime($v): void { $this->start_time = (string)$v; }
    public function setEndTime($v): void { $this->end_time = (string)$v; }
    public function setStatus($v): void { $this->status = (string)$v; }
    public function setCreatedAt($v): void { $this->created_at = $v !== null ? (string)$v : null; }
    public function setUpdatedAt($v): void { $this->updated_at = $v !== null ? (string)$v : null; }

    // Helpers confort
    public function isAvailable(): bool   { return $this->status === 'available'; }
    public function isReserved(): bool    { return $this->status === 'reserved'; }
    public function isUnavailable(): bool { return $this->status === 'unavailable'; }

    public function getStartDateTime(): \DateTime { return new \DateTime($this->date.' '.$this->start_time); }
    public function getEndDateTime(): \DateTime   { return new \DateTime($this->date.' '.$this->end_time); }
}
