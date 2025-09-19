<?php

declare(strict_types=1);

namespace App\models;

use PDO;
use InvalidArgumentException;

final class UserManager extends DBManager
{
    /* ---------- Retour tableaux (legacy) ---------- */

    public function findByEmail(string $email): ?array
    {
        $st = $this->db()->prepare("SELECT * FROM users WHERE email = :e LIMIT 1");
        $st->execute([':e' => $email]);
        return $st->fetch() ?: null;
    }

    public function getById(int $id): ?array
    {
        $st = $this->db()->prepare("SELECT * FROM users WHERE id = :id");
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    }

    public function coaches(): array
    {
        return $this->db()->query("SELECT id,first_name,last_name FROM users WHERE role='coach' ORDER BY first_name")->fetchAll();
    }

    public function adherentsOfCoach(int $coachId): array
    {
        $st = $this->db()->prepare("SELECT id,first_name,last_name FROM users WHERE role='adherent' AND coach_id=:c ORDER BY first_name");
        $st->execute([':c' => $coachId]);
        return $st->fetchAll();
    }
    
    public function updatePasswordHash(int $userId, string $newHash): bool
    {
        // Si tu as encore la colonne legacy `password`, on la purge :
        $sql = "UPDATE users
            SET password_hash = :h, password = NULL, updated_at = NOW()
            WHERE id = :id";

        // Si ta table n’a **pas** de colonne `password`, utilise plutôt :
        // $sql = "UPDATE users SET password_hash = :h, updated_at = NOW() WHERE id = :id";

        $st = $this->db()->prepare($sql);
        return $st->execute([':h' => $newHash, ':id' => $userId]);
    }


    /* ---------- Version ENTITÉS ---------- */

    private function toEntity(array $row): UserEntity
    {
        return (new UserEntity())->fill($row);
    }

    public function findEntityById(int $id): ?UserEntity
    {
        $row = $this->getById($id);
        return $row ? $this->toEntity($row) : null;
    }

    public function findEntityByEmail(string $email): ?UserEntity
    {
        $row = $this->findByEmail($email);
        return $row ? $this->toEntity($row) : null;
    }

    private function ensureHash(array $data): string
    {
        // 1) si on nous passe déjà un hash valide (bcrypt/argon), on le garde
        if (!empty($data['password_hash'])) {
            $info = password_get_info($data['password_hash']);
            if (($info['algo'] ?? 0) !== 0) {
                return $data['password_hash'];
            }
        }
        // 2) sinon on exige un password en clair et on le hash
        if (empty($data['password'])) {
            throw new InvalidArgumentException('password manquant pour création utilisateur');
        }
        return password_hash((string)$data['password'], PASSWORD_DEFAULT);
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO users
                (first_name,last_name,email,password_hash,phone,address,role,gender,age,coach_id,created_at,updated_at)
                VALUES
                (:fn,:ln,:em,:phash,:ph,:ad,:ro,:ge,:ag,:ci,NOW(),NOW())";

        $phash = $this->ensureHash($data);

        $this->db()->prepare($sql)->execute([
            ':fn'   => $data['first_name'],
            ':ln'   => $data['last_name'],
            ':em'   => $data['email'],
            ':phash' => $phash,
            ':ph'   => $data['phone']   ?? null,
            ':ad'   => $data['address'] ?? null,
            ':ro'   => $data['role'],
            ':ge'   => $data['gender']  ?? null,
            ':ag'   => $data['age']     ?? null,
            ':ci'   => $data['coach_id'] ?? null,
        ]);

        return (int)$this->db()->lastInsertId();
    }

    public function createEntity(array $data): UserEntity
    {
        $id  = $this->create($data);
        $row = $this->getById($id) ?? [];
        return $this->toEntity($row);
    }
}
