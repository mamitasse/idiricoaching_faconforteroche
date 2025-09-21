<?php
declare(strict_types=1);

namespace App\models;

/**
 * Hydrate une entité depuis un tableau PDO::FETCH_ASSOC.
 * Convertit automatiquement snake_case -> setStudlyCase:
 *  ex: password_hash -> setPasswordHash(...)
 */
abstract class AbstractEntity
{
    public function fill(array $row): static
    {
        foreach ($row as $key => $value) {
            $studly = str_replace(' ', '', ucwords(str_replace('_', ' ', (string)$key)));
            $setter = 'set' . $studly;
            if (method_exists($this, $setter)) {
                $this->{$setter}($value);
            }
        }
        return $this;
    }
}

