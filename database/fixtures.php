<?php
/**
 * ------------------------------------------------------------
 * FICHIER : database/fixtures.php
 * ------------------------------------------------------------
 * Objectif :
 *   - Remettre la base dans un état propre pour la démonstration
 *   - Insérer uniquement :
 *       * 2 coachs : Nadia et Sabrina
 *       * 3 adhérents : Alice, Bob et Charles
 *
 * IMPORTANT :
 *   - Les créneaux horaires (08h–20h) NE SONT PAS dans la base.
 *     Ils sont générés automatiquement dans le code (dashboard).
 *
 *   - Les réservations ne sont PAS pré-créées.
 *     Elles seront créées lorsque l’adhérent clique sur "Réserver".
 *
 * Ce script :
 *   - vide (TRUNCATE) les tables users / slots / reservations
 *   - insère des données contrôlées de test
 * ------------------------------------------------------------
 */

declare(strict_types=1);

// Chargement de la configuration MySQL
require __DIR__ . '/../config/_config.php';

// Cohérence des dates
date_default_timezone_set('Europe/Paris');

/**
 * Fonction utilitaire d'affichage (CLI ou navigateur)
 */
function out(string $message): void
{
    if (PHP_SAPI === 'cli') {
        echo $message . PHP_EOL;
    } else {
        echo "<pre style='margin:0'>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</pre>";
    }
}

// ------------------------------------------------------------
// 1) Connexion à la base
// ------------------------------------------------------------
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    out("Connexion à la base '" . DB_NAME . "' : OK");
} catch (PDOException $e) {
    out("❌ ERREUR de connexion : " . $e->getMessage());
    exit;
}

/**
 * Fonction helper pour exécuter un SQL simple avec un message clair
 */
function runSql(PDO $pdo, string $label, string $sql): void
{
    out("---- $label ----");
    try {
        $pdo->exec($sql);
        out("OK");
    } catch (PDOException $e) {
        out("❌ ERREUR ($label) : " . $e->getMessage());
    }
}

// ------------------------------------------------------------
// 2) Vidage des tables (RESET total)
// ------------------------------------------------------------
//
// On coupe les contraintes FK pour TRUNCATE proprement.
// ------------------------------------------------------------

runSql($pdo, "Désactiver les contraintes FK", "SET FOREIGN_KEY_CHECKS = 0");

runSql($pdo, "Vider la table reservations", "TRUNCATE TABLE reservations");
runSql($pdo, "Vider la table slots",        "TRUNCATE TABLE slots");
runSql($pdo, "Vider la table users",        "TRUNCATE TABLE users");

runSql($pdo, "Réactiver les contraintes FK", "SET FOREIGN_KEY_CHECKS = 1");

// ------------------------------------------------------------
// 3) Création des coachs (Nadia + Sabrina)
// ------------------------------------------------------------
//
// Mot de passe unique pour simplifier l'évaluation : test1234
// Tous les utilisateurs auront ce mot de passe.
// ------------------------------------------------------------

$plainPassword = 'test1234';
$hashPassword  = password_hash($plainPassword, PASSWORD_BCRYPT);

out("Mot de passe utilisé pour tous les comptes : " . $plainPassword);

try {
    // Préparation de la requête INSERT pour les coachs
    $insertCoachQuery = $pdo->prepare("
        INSERT INTO users (
          first_name, last_name, email,
          password_hash, gender, role
        ) VALUES (
          :first_name, :last_name, :email,
          :password_hash, :gender, 'coach'
        )
    ");

    // --- Coach Nadia ---
    $insertCoachQuery->execute([
        ':first_name'    => 'Nadia',
        ':last_name'     => 'Coach',
        ':email'         => 'nadia@coaching.test',
        ':password_hash' => $hashPassword,
        ':gender'        => 'female',
    ]);
    $nadiaId = (int)$pdo->lastInsertId();

    // --- Coach Sabrina ---
    $insertCoachQuery->execute([
        ':first_name'    => 'Sabrina',
        ':last_name'     => 'Coach',
        ':email'         => 'sabrina@coaching.test',
        ':password_hash' => $hashPassword,
        ':gender'        => 'female',
    ]);
    $sabrinaId = (int)$pdo->lastInsertId();

    out("Coachs créés : Nadia (id=$nadiaId), Sabrina (id=$sabrinaId)");

} catch (PDOException $e) {
    out("❌ ERREUR lors de l'insertion des coachs : " . $e->getMessage());
    exit;
}

// ------------------------------------------------------------
// 4) Création des adhérents de démonstration
// ------------------------------------------------------------
//
// Tous ont le même mot de passe : test1234
// Ils sont rattachés à un coach
// ------------------------------------------------------------

$adherents = [
    [
        'first_name' => 'Alice',
        'last_name'  => 'Durand',
        'email'      => 'alice@demo.test',
        'gender'     => 'female',
        'coach_id'   => $nadiaId,
    ],
    [
        'first_name' => 'Bob',
        'last_name'  => 'Martin',
        'email'      => 'bob@demo.test',
        'gender'     => 'male',
        'coach_id'   => $nadiaId,
    ],
    [
        'first_name' => 'Charles',
        'last_name'  => 'Petit',
        'email'      => 'charles@demo.test',
        'gender'     => 'male',
        'coach_id'   => $sabrinaId,
    ],
];

try {
    // Requête INSERT pour les adhérents
    $insertAdherentQuery = $pdo->prepare("
        INSERT INTO users (
          first_name, last_name, email,
          password_hash, gender, role, coach_id
        ) VALUES (
          :first_name, :last_name, :email,
          :password_hash, :gender, 'adherent', :coach_id
        )
    ");

    foreach ($adherents as $adherent) {

        $insertAdherentQuery->execute([
            ':first_name'    => $adherent['first_name'],
            ':last_name'     => $adherent['last_name'],
            ':email'         => $adherent['email'],
            ':password_hash' => $hashPassword,
            ':gender'        => $adherent['gender'],
            ':coach_id'      => $adherent['coach_id'],
        ]);

        $adherentId = (int)$pdo->lastInsertId();
        out("Adhérent créé : {$adherent['email']} (id=$adherentId)");
    }

} catch (PDOException $e) {
    out("❌ ERREUR lors de l'insertion des adhérents : " . $e->getMessage());
    exit;
}

// ------------------------------------------------------------
// 5) Fin de l’installation des fixtures
// ------------------------------------------------------------
out("🎉 Fixtures installées avec succès dans la base '" . DB_NAME . "' !");
