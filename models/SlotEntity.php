<?php
declare(strict_types=1);

namespace App\models;

use DateTime;

/**
 * SlotEntity
 * ----------
 * Représente un créneau de la table `slots`.
 * On garde `date`, `start_time`, `end_time` en texte (YYYY-MM-DD / HH:MM:SS)
 * pour correspondre 1:1 à la BDD, + helpers pour les manipuler en DateTime.
 */
final class SlotEntity extends AbstractEntity
{
    private ?int $id = null;
    private int $coach_id = 0;

    /** Date SQL (YYYY-MM-DD) */
    private string $date = '';

    /** Heures SQL (HH:MM:SS) */
    private string $start_time = '';
    private string $end_time   = '';

    /** Statut: available | reserved | unavailable */
    private string $status = 'available';

    private ?string $created_at = null;
    private ?string $updated_at = null;

    // -------- Getters --------
    public function getId(): ?int            { return $this->id; }
    public function getCoachId(): int        { return $this->coach_id; }
    public function getDate(): string        { return $this->date; }
    public function getStartTime(): string   { return $this->start_time; }
    public function getEndTime(): string     { return $this->end_time; }
    public function getStatus(): string      { return $this->status; }
    public function getCreatedAt(): ?string  { return $this->created_at; }
    public function getUpdatedAt(): ?string  { return $this->updated_at; }

    // -------- Setters (hydratation) --------
    public function setId($value): void         { $this->id = ($value === null ? null : (int)$value); }
    public function setCoachId($value): void    { $this->coach_id = (int)$value; }
    public function setDate($value): void       { $this->date = (string)$value; }
    public function setStartTime($value): void  { $this->start_time = (string)$value; }
    public function setEndTime($value): void    { $this->end_time = (string)$value; }
    public function setStatus($value): void     { $this->status = (string)$value; }
    public function setCreatedAt($value): void  { $this->created_at = $value !== null ? (string)$value : null; }
    public function setUpdatedAt($value): void  { $this->updated_at = $value !== null ? (string)$value : null; }

    // -------- Helpers métier --------
    public function isAvailable(): bool      { return $this->status === 'available'; }
    public function isReserved(): bool       { return $this->status === 'reserved'; }
    public function isUnavailable(): bool    { return $this->status === 'unavailable'; }

    /** Construit un DateTime "début" à partir de date + start_time */
    public function getStartDateTime(): DateTime
    {
        return new DateTime($this->date.' '.$this->start_time);
    }

    /** Construit un DateTime "fin" à partir de date + end_time */
    public function getEndDateTime(): DateTime
    {
        return new DateTime($this->date.' '.$this->end_time);
    }
}
