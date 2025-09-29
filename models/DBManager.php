<?php
declare(strict_types=1);

namespace App\models;

use PDO;
use PDOException;
use PDOStatement;

/**
 * DBManager
 * =========
 * Classe de base pour tous les *Managers* d'accès aux données.
 * - Fournit une connexion PDO unique et partagée (singleton statique) pour tout le process.
 * - Active les bonnes options PDO (exceptions, fetch associatif, no emulate prepares).
 * - Expose des helpers clairs et documentés pour exécuter des requêtes et gérer les transactions.
 *
 * Bonnes pratiques appliquées :
 * - Noms explicites (pas de variables "lettres" seules).
 * - Méthodes en lowerCamelCase (convention PHP).
 * - Taper les signatures et retours (strict_types=1).
 *
 * Utilisation type :
 *   class UserManager extends DBManager {
 *       public function findByEmail(string $email): ?array {
 *           $sql = 'SELECT * FROM users WHERE email = :email LIMIT 1';
 *           return $this->fetchOne($sql, [':email' => $email]);
 *       }
 *   }
 */
class DBManager
{
    /**
     * Connexion PDO partagée entre toutes les instances (singleton statique).
     * Pourquoi ? Éviter d'ouvrir 50 connexions si on instancie 50 managers.
     */
    private static ?PDO $sharedPdoConnection = null;

    /**
     * Retourne la connexion PDO prête à l'emploi.
     * - Ou bien réutilise la connexion partagée si elle existe déjà.
     * - Déclenche une PDOException détaillée en DEV ; message générique en PROD.
     */
    protected function db(): PDO
    {
        if (self::$sharedPdoConnection instanceof PDO) {
            return self::$sharedPdoConnection;
        }

        try {
            $pdoConnection = new PDO(
                DB_DSN,           // ex : 'mysql:host=127.0.0.1;dbname=coaching_db;charset=utf8mb4'
                DB_USER,          // ex : 'root'
                DB_PASS,          // ex : ''
                [
                    // Lève une exception sur les erreurs SQL (plus simple à gérer proprement).
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    // Renvoie des tableaux associatifs par défaut (clés = noms de colonnes).
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // Utilise les vrais prepared statements côté serveur (plus sûr).
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );

            self::$sharedPdoConnection = $pdoConnection;
            return $pdoConnection;
        } catch (PDOException $exception) {
            // En DEV : on laisse l'exception complète pour faciliter le debug.
            if (defined('IN_DEV') && IN_DEV) {
                throw $exception;
            }
            // En PROD : on ne divulgue pas de détails sensibles.
            throw new PDOException('Database connection failed.');
        }
    }

    /* =========================================================
       Helpers bas-niveau : exécuter des requêtes simplement
       ========================================================= */

    /**
     * Prépare et exécute une requête SQL avec paramètres nommés.
     *
     * @param string               $sql         Requête SQL avec placeholders nommés (:id, :email, ...)
     * @param array<string,mixed>  $parameters  Tableau associatif des valeurs à binder
     * @return PDOStatement                      Statement déjà exécuté (prêt pour fetch())
     */
    protected function prepareAndExecute(string $sql, array $parameters = []): PDOStatement
    {
        $preparedStatement = $this->db()->prepare($sql);
        $preparedStatement->execute($parameters);
        return $preparedStatement;
    }

    /**
     * Récupère une seule ligne (ou null si aucune).
     */
    protected function fetchOne(string $sql, array $parameters = []): ?array
    {
        $preparedStatement = $this->prepareAndExecute($sql, $parameters);
        $row = $preparedStatement->fetch(); // FETCH_ASSOC par défaut (défini dans db())
        return ($row === false) ? null : $row;
    }

    /**
     * Récupère toutes les lignes (tableau vide si aucune).
     */
    protected function fetchAll(string $sql, array $parameters = []): array
    {
        $preparedStatement = $this->prepareAndExecute($sql, $parameters);
        return $preparedStatement->fetchAll();
    }

    /**
     * Retourne le dernier ID inséré (utile après un INSERT).
     */
    protected function lastInsertId(): int
    {
        return (int)$this->db()->lastInsertId();
    }

    /* =========================================================
       Transactions : pour garantir l’atomicité de suites d’actions
       ========================================================= */

    /** Démarre une transaction (BEGIN). */
    protected function beginTransaction(): void
    {
        $this->db()->beginTransaction();
    }

    /** Valide la transaction (COMMIT). */
    protected function commitTransaction(): void
    {
        $this->db()->commit();
    }

    /** Annule la transaction en cours (ROLLBACK) si nécessaire. */
    protected function rollbackTransaction(): void
    {
        $pdoConnection = $this->db();
        if ($pdoConnection->inTransaction()) {
            $pdoConnection->rollBack();
        }
    }

    /**
     * Exécute un bloc de code dans une transaction.
     * - Si aucune transaction n’est en cours, on en démarre une et on commit/rollback à la fin.
     * - Si une transaction est déjà ouverte (appel imbriqué), on réutilise l’existante.
     *
     * @template T
     * @param callable(PDO):T $callback  Fonction à exécuter ; reçoit la PDO si besoin
     * @return T                         Résultat du callback
     */
    protected function withTransaction(callable $callback)
    {
        $pdoConnection = $this->db();
        $isAlreadyInTransaction = $pdoConnection->inTransaction();

        if (!$isAlreadyInTransaction) {
            $pdoConnection->beginTransaction();
        }

        try {
            $result = $callback($pdoConnection);

            if (!$isAlreadyInTransaction) {
                $pdoConnection->commit();
            }
            return $result;
        } catch (\Throwable $throwable) {
            if (!$isAlreadyInTransaction && $pdoConnection->inTransaction()) {
                $pdoConnection->rollBack();
            }
            throw $throwable;
        }
    }
}
