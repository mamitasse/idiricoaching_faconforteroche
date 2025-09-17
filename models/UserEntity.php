<?php
declare(strict_types=1);

namespace App\models;

final class UserEntity extends AbstractEntity
{
    private ?int $id = null;
    private string $first_name = '';
    private string $last_name  = '';
    private string $email      = '';
    private ?string $role      = null;
    private ?int $coach_id     = null;
    private ?string $phone     = null;
    private ?string $address   = null;
    private ?int $age          = null;
    private ?string $gender    = null;

    // Getters
    public function getId(): ?int             { return $this->id; }
    public function getFirstName(): string    { return $this->first_name; }
    public function getLastName(): string     { return $this->last_name; }
    public function getEmail(): string        { return $this->email; }
    public function getRole(): ?string        { return $this->role; }
    public function getCoachId(): ?int        { return $this->coach_id; }
    public function getPhone(): ?string       { return $this->phone; }
    public function getAddress(): ?string     { return $this->address; }
    public function getAge(): ?int            { return $this->age; }
    public function getGender(): ?string      { return $this->gender; }

    // Setters (hydratation)
    public function setId($v): void           { $this->id = $v !== null ? (int)$v : null; }
    public function setFirstName($v): void    { $this->first_name = (string)$v; }
    public function setLastName($v): void     { $this->last_name = (string)$v; }
    public function setEmail($v): void        { $this->email = (string)$v; }
    public function setRole($v): void         { $this->role = $v !== null ? (string)$v : null; }
    public function setCoachId($v): void      { $this->coach_id = $v !== null ? (int)$v : null; }
    public function setPhone($v): void        { $this->phone = $v !== null ? (string)$v : null; }
    public function setAddress($v): void      { $this->address = $v !== null ? (string)$v : null; }
    public function setAge($v): void          { $this->age = $v !== null && $v!=='' ? (int)$v : null; }
    public function setGender($v): void       { $this->gender = $v !== null ? (string)$v : null; }

    // Confort
    public function getFullName(): string     { return trim($this->first_name.' '.$this->last_name); }
}

