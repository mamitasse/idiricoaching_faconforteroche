<?php
declare(strict_types=1);

namespace App\models;

/**
 * AbstractEntity
 * --------------
 * Base des entités : propose un "fill()" générique qui appelle
 * automatiquement les setters à partir d’un tableau (provenant de PDO).
 *
 * Exemple: clé 'first_name' appellera setFirstName($value) si la méthode existe.
 */
abstract class AbstractEntity
{
    /**
     * Hydrate l’entité à partir d’un tableau associatif BDD.
     * - Convertit snake_case -> CamelCase pour le nom du setter
     */
    public function fill(array $row): static
    {
        foreach ($row as $key => $value) {
            $method = 'set' . self::snakeToCamel($key);
            if (method_exists($this, $method)) {
                $this->{$method}($value);
            }
        }
        return $this;
    }

    /** Convertit `first_name` -> `FirstName` */
    protected static function snakeToCamel(string $snake): string
    {
        $parts = explode('_', $snake);
        $parts = array_map(fn($p) => ucfirst(strtolower($p)), $parts);
        return implode('', $parts);
    }
}
