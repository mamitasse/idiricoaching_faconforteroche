<?php
declare(strict_types=1);

namespace App\models;

/**
 * UserEntity
 * ----------
 * Représentation métier d’un utilisateur (table `users`).
 * On conserve les noms de colonnes SQL en snake_case comme propriétés privées
 * pour que l’hydratation via AbstractEntity::fill() reste 1:1 avec la BDD.
 * Les méthodes publiques (getters/setters) sont en camelCase (conforme aux bonnes pratiques PHP).
 */
final class UserEntity extends AbstractEntity
{
    /** Identifiant (PK) */
    private ?int $id = null;

    /** Prénom / Nom / Email */
    private string $first_name = '';
    private string $last_name  = '';
    private string $email      = '';

    /** Rôle “adherent” | “coach” */
    private ?string $role = null;

    /** Coach associé pour un adhérent (nullable) */
    private ?int $coach_id = null;

    /** Infos annexes */
    private ?string $phone   = null;
    private ?string $address = null;
    private ?int $age        = null;
    private ?string $gender  = null;

    /** Hash sécurisé du mot de passe (bcrypt/argon). Champs moderne recommandé. */
    private string $password_hash = '';

    /** (Optionnel) Ancien champ si tu avais stocké le mot de passe en clair (= à migrer) */
    private ?string $password = null;

    /* ======================
       Getters (lecture)
       ====================== */

    public function getId(): ?int            { return $this->id; }
    public function getFirstName(): string   { return $this->first_name; }
    public function getLastName(): string    { return $this->last_name; }
    public function getEmail(): string       { return $this->email; }
    public function getRole(): ?string       { return $this->role; }
    public function getCoachId(): ?int       { return $this->coach_id; }
    public function getPhone(): ?string      { return $this->phone; }
    public function getAddress(): ?string    { return $this->address; }
    public function getAge(): ?int           { return $this->age; }
    public function getGender(): ?string     { return $this->gender; }

    /** Hash sécurisé pour password_verify() */
    public function getPasswordHash(): string { return $this->password_hash; }

    /** (legacy) Mot de passe en clair si jamais il existait encore en BDD */
    public function getPassword(): ?string    { return $this->password; }

    /* ======================
       Setters (écriture)
       ====================== */

    public function setId($value): void         { $this->id = $value !== null ? (int)$value : null; }
    public function setFirstName($value): void  { $this->first_name = (string)$value; }
    public function setLastName($value): void   { $this->last_name  = (string)$value; }
    public function setEmail($value): void      { $this->email      = (string)$value; }
    public function setRole($value): void       { $this->role       = $value !== null ? (string)$value : null; }
    public function setCoachId($value): void    { $this->coach_id   = $value !== null ? (int)$value : null; }
    public function setPhone($value): void      { $this->phone      = $value !== null ? (string)$value : null; }
    public function setAddress($value): void    { $this->address    = $value !== null ? (string)$value : null; }
    public function setAge($value): void        { $this->age        = ($value !== null && $value !== '') ? (int)$value : null; }
    public function setGender($value): void     { $this->gender     = $value !== null ? (string)$value : null; }

    /** Setters indispensables pour l’authentification */
    public function setPasswordHash($value): void { $this->password_hash = (string)$value; }
    public function setPassword($value): void     { $this->password      = $value !== null ? (string)$value : null; }

    /* ======================
       Helpers de confort
       ====================== */

    /** Nom complet formatté “Prénom Nom” */
    public function getFullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
