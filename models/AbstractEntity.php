<?php
declare(strict_types=1);

namespace App\models;

/**
 * Base simple pour hydrater une entité à partir d'un tableau issu de la BDD.
 * Convertit les clés snake_case en Setters PascalCase :
 *  ex: first_name  -> setFirstName()
 *      coach_id    -> setCoachId()
 */
abstract class AbstractEntity
{
    /** Hydrate l'entité et renvoie $this pour chaînage */
    public function fill(array $row): static
    {
        foreach ($row as $key => $val) {
            $method = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', (string)$key)));
            if (method_exists($this, $method)) {
                $this->{$method}($val);
            }
        }
        return $this;
    }

    /** (optionnel) Exporte les propriétés publiques connues via getters getXxx() */
    public function toArray(): array
    {
        $out = [];
        foreach (get_class_methods($this) as $m) {
            if (str_starts_with($m, 'get') && $m !== 'getFullName') {
                $key = lcfirst(preg_replace('/^get/', '', $m));
                $key = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $key)); // camelCase -> snake_case
                $out[$key] = $this->{$m}();
            }
        }
        return $out;
    }
}
