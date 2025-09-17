<?php
declare(strict_types=1);

namespace App\models;

use PDO;

final class UserManager extends DBManager
{
    /* ---------- Retour tableaux (legacy) ---------- */

    public function findByEmail(string $email): ?array
    {
        $st = $this->db()->prepare("SELECT * FROM users WHERE email = :e LIMIT 1");
        $st->execute([':e'=>$email]);
        return $st->fetch() ?: null;
    }

    public function getById(int $id): ?array
    {
        $st = $this->db()->prepare("SELECT * FROM users WHERE id = :id");
        $st->execute([':id'=>$id]);
        return $st->fetch() ?: null;
    }

    public function coaches(): array
    {
        return $this->db()->query("SELECT id,first_name,last_name FROM users WHERE role='coach' ORDER BY first_name")->fetchAll();
    }

    public function adherentsOfCoach(int $coachId): array
    {
        $st = $this->db()->prepare("SELECT id,first_name,last_name FROM users WHERE role='adherent' AND coach_id=:c ORDER BY first_name");
        $st->execute([':c'=>$coachId]);
        return $st->fetchAll();
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

    public function create(array $data): int
    {
        // on cible password_hash (consistent avec nos scripts d’intégration)
        $sql = "INSERT INTO users
                (first_name,last_name,email,password_hash,phone,address,role,gender,age,coach_id,created_at,updated_at)
                VALUES
                (:fn,:ln,:em,:phash,:ph,:ad,:ro,:ge,:ag,:ci,NOW(),NOW())";

        $phash = $data['password_hash'] ?? ($data['password'] ?? null); // compat si déjà hashé dans 'password'

        $this->db()->prepare($sql)->execute([
            ':fn'=>$data['first_name'], ':ln'=>$data['last_name'], ':em'=>$data['email'],
            ':phash'=>$phash,
            ':ph'=>$data['phone'] ?? null, ':ad'=>$data['address'] ?? null,
            ':ro'=>$data['role'], ':ge'=>$data['gender'] ?? null, ':ag'=>$data['age'] ?? null,
            ':ci'=>$data['coach_id'] ?? null,
        ]);

        return (int)$this->db()->lastInsertId();
    }

    /** (optionnel) Constructeur d’entité à partir du dernier INSERT */
    public function createEntity(array $data): UserEntity
    {
        $id = $this->create($data);
        $row = $this->getById($id) ?? [];
        return $this->toEntity($row);
    }
}
