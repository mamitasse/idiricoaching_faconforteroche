<?php
declare(strict_types=1);

namespace App\models;

use PDO;
use InvalidArgumentException;

/**
 * UserManager
 * -----------
 * Accès aux données "users" (SELECT/INSERT/UPDATE).
 * Retourne des entités (UserEntity) pour les cas métier,
 * et parfois des tableaux associatifs pour les cas d’affichage simples.
 */
final class UserManager extends DBManager
{
    /** Convertit un row PDO en UserEntity */
    private function toEntity(?array $row): ?UserEntity
    {
        if (!$row) return null;
        return (new UserEntity())->fill($row);
    }

    /**
     * Retourne un hash robuste à stocker.
     * - si $data['password_hash'] contient déjà un vrai hash bcrypt/argon → on garde
     * - sinon si $data['password'] (clair) existe → on hash avec PASSWORD_DEFAULT
     */
    private function ensureHash(array $data): string
    {
        if (!empty($data['password_hash'])) {
            $info = password_get_info((string)$data['password_hash']);
            if (($info['algo'] ?? 0) !== 0) {
                return (string)$data['password_hash'];
            }
        }
        if (empty($data['password'])) {
            throw new InvalidArgumentException('password manquant pour création utilisateur');
        }
        return password_hash((string)$data['password'], PASSWORD_DEFAULT);
    }

    // ===== ENTITÉS =====

    /** Récupère une entité par id */
    public function findEntityById(int $id): ?UserEntity
    {
        $statement = $this->db()->prepare('SELECT * FROM users WHERE id = :id');
        $statement->execute([':id' => $id]);
        return $this->toEntity($statement->fetch(PDO::FETCH_ASSOC) ?: null);
    }

    /** Récupère une entité par email */
    public function findEntityByEmail(string $email): ?UserEntity
    {
        $statement = $this->db()->prepare('SELECT * FROM users WHERE email = :e LIMIT 1');
        $statement->execute([':e' => $email]);
        return $this->toEntity($statement->fetch(PDO::FETCH_ASSOC) ?: null);
    }

    /** Liste d’entités par rôle (coach/adherent) */
    public function listEntitiesByRole(string $role): array
    {
        $statement = $this->db()->prepare('SELECT * FROM users WHERE role = :r ORDER BY first_name, last_name');
        $statement->execute([':r' => $role]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r) => $this->toEntity($r), $rows);
    }

    /** Raccourci : tous les coachs (entités) */
    public function coachesEntities(): array
    {
        return $this->listEntitiesByRole('coach');
    }

    /** Tous les adhérents rattachés à un coach (entités) */
    public function adherentsOfCoachEntities(int $coachId): array
    {
        $statement = $this->db()->prepare(
            'SELECT * FROM users WHERE role = "adherent" AND coach_id = :c ORDER BY first_name, last_name'
        );
        $statement->execute([':c' => $coachId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r) => $this->toEntity($r), $rows);
    }

    // ===== ECRITURES =====

    /** Crée un utilisateur et retourne l’ID inséré */
    public function create(array $data): int
    {
        $passwordHash = $this->ensureHash($data);

        $sql = 'INSERT INTO users
                (first_name, last_name, email, password_hash, phone, address, role, gender, age, coach_id, created_at, updated_at)
                VALUES
                (:fn, :ln, :em, :ph, :phn, :addr, :role, :gender, :age, :coach, NOW(), NOW())';

        $ok = $this->db()->prepare($sql)->execute([
            ':fn'     => trim((string)($data['first_name'] ?? '')),
            ':ln'     => trim((string)($data['last_name']  ?? '')),
            ':em'     => trim(strtolower((string)($data['email'] ?? ''))),
            ':ph'     => $passwordHash,
            ':phn'    => $data['phone']   ?? null,
            ':addr'   => $data['address'] ?? null,
            ':role'   => $data['role']    ?? 'adherent',
            ':gender' => $data['gender']  ?? null,
            ':age'    => isset($data['age']) && $data['age'] !== '' ? (int)$data['age'] : null,
            ':coach'  => isset($data['coach_id']) && $data['coach_id'] !== '' ? (int)$data['coach_id'] : null,
        ]);

        return $ok ? (int)$this->db()->lastInsertId() : 0;
    }

    /** Crée et renvoie l’entité créée (pratique côté contrôleur) */
    public function createEntity(array $data): UserEntity
    {
        $id = $this->create($data);
        $entity = $this->findEntityById($id);
        if (!$entity) {
            throw new \RuntimeException('Création utilisateur échouée');
        }
        return $entity;
    }

    /** Met à jour le hash du mot de passe pour un user donné */
    public function updatePasswordHash(int $id, string $newHash): bool
    {
        $statement = $this->db()->prepare(
            'UPDATE users SET password_hash = :h, updated_at = NOW() WHERE id = :id'
        );
        return $statement->execute([':h' => $newHash, ':id' => $id]);
    }
}
