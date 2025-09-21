<?php
declare(strict_types=1);

namespace App\models;

use PDO;
use InvalidArgumentException;

final class UserManager extends DBManager
{
    private function toEntity(?array $row): ?UserEntity
    {
        if (!$row) return null;
        return (new UserEntity())->fill($row);
    }

    private function ensureHash(array $data): string
    {
        if (!empty($data['password_hash'])) {
            $info = password_get_info((string)$data['password_hash']);
            if (($info['algo'] ?? 0) !== 0) return (string)$data['password_hash'];
        }
        if (empty($data['password'])) {
            throw new InvalidArgumentException('password manquant pour création utilisateur');
        }
        return password_hash((string)$data['password'], PASSWORD_DEFAULT);
    }

    // ===== ENTITÉS =====
    public function findEntityById(int $id): ?UserEntity
    {
        $st = $this->db()->prepare('SELECT * FROM users WHERE id=:id');
        $st->execute([':id'=>$id]);
        return $this->toEntity($st->fetch(PDO::FETCH_ASSOC) ?: null);
    }

    public function findEntityByEmail(string $email): ?UserEntity
    {
        $st = $this->db()->prepare('SELECT * FROM users WHERE email=:e LIMIT 1');
        $st->execute([':e'=>$email]);
        return $this->toEntity($st->fetch(PDO::FETCH_ASSOC) ?: null);
    }

    public function listEntitiesByRole(string $role): array
    {
        $st = $this->db()->prepare('SELECT * FROM users WHERE role=:r ORDER BY first_name,last_name');
        $st->execute([':r'=>$role]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r)=>$this->toEntity($r), $rows);
    }

    public function coachesEntities(): array
    {
        return $this->listEntitiesByRole('coach');
    }

    public function adherentsOfCoachEntities(int $coachId): array
    {
        $st = $this->db()->prepare('SELECT * FROM users WHERE role="adherent" AND coach_id=:c ORDER BY first_name,last_name');
        $st->execute([':c'=>$coachId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r)=>$this->toEntity($r), $rows);
    }

    public function create(array $data): int
    {
        $phash = $this->ensureHash($data);

        $sql = 'INSERT INTO users
                (first_name,last_name,email,password_hash,phone,address,role,gender,age,coach_id,created_at,updated_at)
                VALUES
                (:fn,:ln,:em,:ph,:phn,:addr,:role,:gender,:age,:coach,NOW(),NOW())';

        $ok = $this->db()->prepare($sql)->execute([
            ':fn'     => trim((string)($data['first_name'] ?? '')),
            ':ln'     => trim((string)($data['last_name']  ?? '')),
            ':em'     => trim(strtolower((string)($data['email'] ?? ''))),
            ':ph'     => $phash,
            ':phn'    => $data['phone']   ?? null,
            ':addr'   => $data['address'] ?? null,
            ':role'   => $data['role']    ?? 'adherent',
            ':gender' => $data['gender']  ?? null,
            ':age'    => isset($data['age']) && $data['age'] !== '' ? (int)$data['age'] : null,
            ':coach'  => isset($data['coach_id']) && $data['coach_id'] !== '' ? (int)$data['coach_id'] : null,
        ]);

        return $ok ? (int)$this->db()->lastInsertId() : 0;
    }

    public function createEntity(array $data): UserEntity
    {
        $id = $this->create($data);
        $e  = $this->findEntityById($id);
        if (!$e) throw new \RuntimeException('Création utilisateur échouée');
        return $e;
    }

    public function updatePasswordHash(int $id, string $newHash): bool
    {
        $st = $this->db()->prepare('UPDATE users SET password_hash=:h, updated_at=NOW() WHERE id=:id');
        return $st->execute([':h'=>$newHash, ':id'=>$id]);
    }
}
