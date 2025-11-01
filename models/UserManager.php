<?php
declare(strict_types=1);

namespace App\models;

use PDO;
use DateTime;
use InvalidArgumentException;

/**
 * UserManager (mentor-style)
 * -------------------------
 * - Nommage camelCase
 * - Méthodes explicites
 * - Docblocks clairs
 *
 * Contenu :
 *  - ENTITÉS : findEntityById(), findEntityByEmail(), listEntitiesByRole(), ...
 *  - CRUD : create(), createEntity(), updatePasswordHash()
 *  - Mot de passe oublié :
 *      findByEmail(), findByResetToken(),
 *      setPasswordResetToken(), clearPasswordResetToken(),
 *      updatePassword() (version “texte clair” -> hash ici)
 */
final class UserManager extends DBManager
{
    /* =========================================================
     * Helpers internes
     * ======================================================= */

    /** Convertit un row PDO en UserEntity. */
    private function toEntity(?array $row): ?UserEntity
    {
        if (!$row) {
            return null;
        }
        return (new UserEntity())->fill($row);
    }

    /**
     * Retourne un hash robuste à stocker.
     * - Si $data['password_hash'] contient déjà un vrai hash (bcrypt/argon) → on garde
     * - Sinon si $data['password'] (clair) existe → on hash avec PASSWORD_DEFAULT
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

    /* =========================================================
     * ENTITÉS (orienté domaine)
     * ======================================================= */

    /** Récupère une entité par id. */
    public function findEntityById(int $id): ?UserEntity
    {
        $st = $this->db()->prepare('SELECT * FROM users WHERE id = :id');
        $st->execute([':id' => $id]);
        return $this->toEntity($st->fetch(PDO::FETCH_ASSOC) ?: null);
    }

    /** Récupère une entité par email. */
    public function findEntityByEmail(string $email): ?UserEntity
    {
        $st = $this->db()->prepare('SELECT * FROM users WHERE email = :e LIMIT 1');
        $st->execute([':e' => strtolower(trim($email))]);
        return $this->toEntity($st->fetch(PDO::FETCH_ASSOC) ?: null);
    }

    /** Liste d’entités par rôle (coach/adherent). */
    public function listEntitiesByRole(string $role): array
    {
        $st = $this->db()->prepare('SELECT * FROM users WHERE role = :r ORDER BY first_name, last_name');
        $st->execute([':r' => $role]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $r) => $this->toEntity($r), $rows);
    }

    /** Raccourci : tous les coachs (entités). */
    public function coachesEntities(): array
    {
        return $this->listEntitiesByRole('coach');
    }

    /** Tous les adhérents rattachés à un coach (entités). */
    public function adherentsOfCoachEntities(int $coachId): array
    {
        $st = $this->db()->prepare(
            'SELECT * FROM users WHERE role = "adherent" AND coach_id = :c ORDER BY first_name, last_name'
        );
        $st->execute([':c' => $coachId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $r) => $this->toEntity($r), $rows);
    }

    /* =========================================================
     * ÉCRITURES / CRUD
     * ======================================================= */

    /** Crée un utilisateur et retourne l’ID inséré. */
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

    /** Crée et renvoie l’entité créée (pratique côté contrôleur). */
    public function createEntity(array $data): UserEntity
    {
        $id = $this->create($data);
        $entity = $this->findEntityById($id);
        if (!$entity) {
            throw new \RuntimeException('Création utilisateur échouée');
        }
        return $entity;
    }

    /** Met à jour le hash du mot de passe (hash déjà calculé). */
    public function updatePasswordHash(int $id, string $newHash): bool
    {
        $st = $this->db()->prepare(
            'UPDATE users SET password_hash = :h, updated_at = NOW() WHERE id = :id'
        );
        return $st->execute([':h' => $newHash, ':id' => $id]);
    }

    /**
     * Met à jour le mot de passe à partir du clair (hash ici).
     * Utile pour la phase de reset.
     */
    public function updatePassword(int $id, string $plainPassword): bool
    {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $st = $this->db()->prepare(
            'UPDATE users SET password_hash = :h, updated_at = NOW() WHERE id = :id'
        );
        return $st->execute([':h' => $hash, ':id' => $id]);
    }

    /* =========================================================
     * MOT DE PASSE OUBLIÉ (compat AuthController)
     * ======================================================= */

    /**
     * Retourne la ligne utilisateur par email (array) — pour le flux “forgot”.
     * NB : on renvoie un array (pas une entité) pour rester simple côté contrôleur.
     */
    public function findByEmail(string $email): ?array
    {
        $st = $this->db()->prepare('SELECT * FROM users WHERE email = :e LIMIT 1');
        $st->execute([':e' => strtolower(trim($email))]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        return $row ?: null;
    }

    /**
     * Pose un token de réinitialisation valable $ttlMinutes minutes (défaut 60)
     * et renvoie le token.
     */
    public function setPasswordResetToken(int $userId, int $ttlMinutes = 60): string
    {
        $token    = bin2hex(random_bytes(32)); // 64 chars
        $expireAt = (new DateTime("+{$ttlMinutes} minutes"))->format('Y-m-d H:i:s');

        $sql = "UPDATE users
                SET reset_token = :t, reset_expires_at = :e, updated_at = NOW()
                WHERE id = :id";
        $st  = $this->db()->prepare($sql);
        $st->execute([':t' => $token, ':e' => $expireAt, ':id' => $userId]);

        return $token;
    }

    /**
     * Retourne l'utilisateur par token de reset encore valide.
     * (NULL si expiré ou inconnu)
     */
    public function findByResetToken(string $token): ?array
    {
        $sql = "SELECT * FROM users
                WHERE reset_token = :t
                  AND reset_expires_at IS NOT NULL
                  AND reset_expires_at >= NOW()
                LIMIT 1";
        $st  = $this->db()->prepare($sql);
        $st->execute([':t' => $token]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        return $row ?: null;
    }

    /** Supprime le token de reset (après utilisation). */
    public function clearPasswordResetToken(int $userId): bool
    {
        $sql = "UPDATE users
                SET reset_token = NULL, reset_expires_at = NULL, updated_at = NOW()
                WHERE id = :id";
        $st  = $this->db()->prepare($sql);
        return $st->execute([':id' => $userId]);
    }
}
