<?php
declare(strict_types=1);

namespace App\models;

use PDO;

/**
 * Base générique pour les managers/repositories d'entités.
 * Fournit des helpers find/findAll + mapping vers une entité.
 */
abstract class AbstractEntityManager extends DBManager
{
    /** Nom de la table SQL, ex: 'users' */
    protected string $table = '';
    /** Nom de la PK (par défaut 'id') */
    protected string $primaryKey = 'id';
    /** Classe d'entité mappée, ex: \App\models\UserEntity::class */
    protected string $entityClass = \stdClass::class;

    /** map 1 ligne en entité */
    protected function mapRow(?array $row)
    {
        if (!$row) return null;
        $class = $this->entityClass;
        return new $class($row);
    }

    /** map plusieurs lignes en entités */
    protected function mapRows(array $rows): array
    {
        $class = $this->entityClass;
        $out = [];
        foreach ($rows as $r) {
            $out[] = new $class($r);
        }
        return $out;
    }

    /** Récupère une entité par id */
    public function find(int $id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey}=:id";
        $st = $this->db()->prepare($sql);
        $st->execute(array('id' => $id));
        return $this->mapRow($st->fetch(PDO::FETCH_ASSOC));
    }

    /** Récupère toutes les entités */
    public function findAll(): array
    {
        $rows = $this->db()->query("SELECT * FROM {$this->table}")->fetchAll(PDO::FETCH_ASSOC);
        return $this->mapRows($rows);
    }
}