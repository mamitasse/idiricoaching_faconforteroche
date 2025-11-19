<?php
/**
 * ------------------------------------------------------------
 * FICHIER : database/fixtures.php
 * ------------------------------------------------------------
 * Objectif :
 *   - Remettre la base dans un état de DÉMO propre
 *   - Créer uniquement :
 *       * 2 coachs : Nadia & Sabrina
 *       * 3 adhérents : Alice, Bob, Charles
 *
 * IMPORTANT :
 *   - Les créneaux horaires (08h-20h) sont générés PAR LE CODE,
 *     pas insérés dans la base.
 *   - Les réservations seront créées "en vrai" quand l'adhérent
 *     clique sur "Réserver" dans l'application.
 *
 * Effet du script :
 *   - TRUNCATE (vider) : reservations, slots, users
 *   - Réinsérer les utilisateurs de test
 * ------------------------------------------------------------
 */

declare(strict_types=1);

// 1) Chargement de la configuration (DB_HOST, DB_NAME, etc.)
require __DIR__ . '/../config/_config.php';

// Pour des dates cohérentes
date_default_timezone_set('Europe/Paris');

/**
 * Affichage utilitaire (CLI ou navigateur)
 */
function out(string $msg): void {
    if (PHP_SAPI === 'cli') {
        echo $msg . PHP_EOL;
    } else {
        echo "<pre style='margin:0'>" . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "</pre>";
    }
}

// ------------------------------------------------------------
// 2) Connexion à la base de données
// ------------------------------------------------------------

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    out("Connexion à la base '" . DB_NAME . "' : OK");
} catch (PDOException $e) {
    out("❌ ERREUR de connexion à la base : " . $e->getMessage());
    exit;
}

/**
 * Helper pour exécuter une requête SQL avec un titre lisible.
 */
function runSql(PDO $pdo, string $label, string $sql): void {
    out("---- $label ----");
    try {
        $pdo->exec($sql);
        out("OK");
    } catch (PDOException $e) {
        out("❌ ERREUR ($label) : " . $e->getMessage());
    }
}

// ------------------------------------------------------------
// 3) Vider les tables (reset des données)
// ------------------------------------------------------------
//
// On vide les tables dans l'ordre des dépendances :
//
//   reservations → dépend de slots + users
//   slots        → dépend de users
//   users        → table principale des comptes
//
// On désactive les contraintes de clés étrangères
// pendant les TRUNCATE pour éviter les erreurs.
// ------------------------------------------------------------

runSql($pdo, "Désactiver les contraintes FK", "SET FOREIGN_KEY_CHECKS = 0");

runSql($pdo, "Vider la table reservations", "TRUNCATE TABLE `reservations`");
runSql($pdo, "Vider la table slots",        "TRUNCATE TABLE `slots`");
runSql($pdo, "Vider la table users",        "TRUNCATE TABLE `users`");

runSql($pdo, "Réactiver les contraintes FK", "SET FOREIGN_KEY_CHECKS = 1");

// ------------------------------------------------------------
// 4) Création des coachs : Nadia & Sabrina
// ------------------------------------------------------------
//
// Mot de passe unique pour tous les comptes de DÉMO : test1234
// → simplifie les tests pour le mentor / évaluateur.
// ------------------------------------------------------------

$plainPassword = 'test1234';
$hash = password_hash($plainPassword, PASSWORD_BCRYPT);

out("Mot de passe utilisé pour TOUS les comptes de test : " . $plainPassword);

try {
    // Nadia
    $stmt = $pdo->prepare("
        INSERT INTO `users` (
          `first_name`, `last_name`, `email`,
          `password_hash`, `gender`, `role`
        ) VALUES (
          'Nadia', 'Coach', 'nadia@coaching.test',
          :hash, 'female', 'coach'
        )
    ");
    $stmt->execute([':hash' => $hash]);
    $nadiaId = (int) $pdo->lastInsertId();

    // Sabrina
    $stmt = $pdo->prepare("
        INSERT INTO `users` (
          `first_name`, `last_name`, `email`,
          `password_hash`, `gender`, `role`
        ) VALUES (
          'Sabrina', 'Coach', 'sabrina@coaching.test',
          :hash, 'female', 'coach'
        )
    ");
    $stmt->execute([':hash' => $hash]);
    $sabrinaId = (int) $pdo->lastInsertId();

    out("Coachs créés : Nadia (id=$nadiaId), Sabrina (id=$sabrinaId)");
} catch (PDOException $e) {
    out("❌ ERREUR insertion coachs : " . $e->getMessage());
    exit;
}

// ------------------------------------------------------------
// 5) Création des adhérents de démonstration
// ------------------------------------------------------------
//
// Tous utilisent aussi le mot de passe : test1234
// ------------------------------------------------------------

$adherentsData = [
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
    $stmt = $pdo->prepare("
        INSERT INTO `users` (
          `first_name`, `last_name`, `email`,
          `password_hash`, `gender`, `role`, `coach_id`
        ) VALUES (
          :first_name, :last_name, :email,
          :hash, :gender, 'adherent', :coach_id
        )
    ");

    foreach ($adherentsData as $data) {
        $stmt->execute([
            ':first_name' => $data['first_name'],
            ':last_name'  => $data['last_name'],
            ':email'      => $data['email'],
            ':hash'       => $hash,
            ':gender'     => $data['gender'],
            ':coach_id'   => $data['coach_id'],
        ]);

        $id = (int) $pdo->lastInsertId();
        out("Adhérent créé : {$data['email']} (id=$id, coach_id={$data['coach_id']})");
    }
} catch (PDOException $e) {
    out("❌ ERREUR insertion adhérents : " . $e->getMessage());
    exit;
}

out("🎉 Fixtures installées avec succès dans la base '" . DB_NAME . "' !");
