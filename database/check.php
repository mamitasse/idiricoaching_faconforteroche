<?php
/**
 * database/check.php
 * ------------------------------------------------------------
 * Vérifie que la base IdiriCoaching correspond bien au code :
 * - Tables : users, slots, reservations
 * - Colonnes clés (présence)
 * - Index importants (présence)
 * - Clés étrangères (présence)
 *
 * Affiche un rapport HTML (ou texte en CLI).
 * À SUPPRIMER une fois le diagnostic terminé (car exposé publiquement).
 * ------------------------------------------------------------
 */
declare(strict_types=1);

// 1) Charger la config projet (doit définir DB_HOST, DB_NAME, DB_USER, DB_PASS)
require __DIR__ . '/../config/_config.php';

// 2) Helpers d'affichage
$isCli = (PHP_SAPI === 'cli');
function escapeHtmlLocal(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function lineOut(string $htmlLine): void {
    global $isCli;
    if ($isCli) {
        echo strip_tags($htmlLine) . PHP_EOL;
    } else {
        echo $htmlLine . "\n";
    }
}
function badgeOk(string $txt='OK'): string     { return '<span style="color:#16a34a;font-weight:700">✔ '.$txt.'</span>'; }
function badgeWarn(string $txt='WARN'): string { return '<span style="color:#a16207;font-weight:700">⚠ '.$txt.'</span>'; }
function badgeErr(string $txt='ERR'): string   { return '<span style="color:#dc2626;font-weight:700">✖ '.$txt.'</span>'; }

// 3) Connexion PDO
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (Throwable $e) {
    if (!$isCli) {
        echo '<!doctype html><meta charset="utf-8"><body style="font-family:system-ui;background:#111;color:#eee">';
    }
    lineOut('<h2>Connexion à MySQL</h2>');
    lineOut(badgeErr('Connexion échouée') . ' — ' . escapeHtmlLocal($e->getMessage()));
    exit(1);
}

// 4) Fonctions d’introspection (information_schema)
function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name=?");
    $stmt->execute([DB_NAME, $table]);
    return (bool)$stmt->fetchColumn();
}
function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=? AND table_name=? AND column_name=?");
    $stmt->execute([DB_NAME, $table, $column]);
    return (bool)$stmt->fetchColumn();
}
function indexExists(PDO $pdo, string $table, string $indexName): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=? AND table_name=? AND index_name=?");
    $stmt->execute([DB_NAME, $table, $indexName]);
    return (bool)$stmt->fetchColumn();
}
/** Retourne un tableau des FKs existantes sous forme ["table.column" => "refTable.refCol", ...] */
function foreignKeys(PDO $pdo): array {
    $sql = "
      SELECT k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, k.CONSTRAINT_NAME
      FROM information_schema.KEY_COLUMN_USAGE k
      WHERE k.TABLE_SCHEMA = ?
        AND k.REFERENCED_TABLE_NAME IS NOT NULL
      ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([DB_NAME]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[$row['TABLE_NAME'].'.'.$row['COLUMN_NAME']] = $row['REFERENCED_TABLE_NAME'].'.'.$row['REFERENCED_COLUMN_NAME'];
    }
    return $out;
}

// 5) Spécification attendue par le code
$spec = [
    'users' => [
        'columns' => [
            'id','first_name','last_name','email','password_hash','phone','address',
            'age','gender','role','coach_id','created_at','updated_at'
        ],
        'indexes' => [
            'uk_users_email',   // UNIQUE (email)
            'idx_users_coach',  // coach_id
        ],
        'fks' => [
            'users.coach_id' => 'users.id',
        ],
    ],
    'slots' => [
        'columns' => [
            'id','coach_id','date','start_time','end_time','status','created_at','updated_at'
        ],
        'indexes' => [
            'idx_slots_coach',
            'uk_coach_date_time',  // UNIQUE (coach_id, date, start_time)
        ],
        'fks' => [
            'slots.coach_id' => 'users.id',
        ],
    ],
    'reservations' => [
        'columns' => [
            'id','slot_id','adherent_id','coach_id','status','paid','created_at','updated_at'
        ],
        'indexes' => [
            'idx_res_slot','idx_res_adh','idx_res_coach',
        ],
        'fks' => [
            'reservations.slot_id'     => 'slots.id',
            'reservations.adherent_id' => 'users.id',
            'reservations.coach_id'    => 'users.id',
        ],
    ],
];

// 6) Rapport
if (!$isCli) {
    echo '<!doctype html><meta charset="utf-8">';
    echo '<body style="font-family:system-ui;background:#0b0b0b;color:#e5e7eb;padding:24px">';
    echo '<h1 style="margin:0 0 8px">Diagnostic BDD — IdiriCoaching</h1>';
    echo '<div style="opacity:.8;margin-bottom:16px">Base : <b>'.escapeHtmlLocal(DB_NAME).'</b> — Hôte : <b>'.escapeHtmlLocal(DB_HOST).'</b></div>';
}

$fkMap = foreignKeys($pdo);

foreach ($spec as $tableName => $rules) {
    lineOut('<h2 style="margin:24px 0 6px">Table <code>'.$tableName.'</code></h2>');

    // Table OK ?
    if (!tableExists($pdo, $tableName)) {
        lineOut(badgeErr('table manquante') . ' : ' . $tableName);
        continue;
    }
    lineOut(badgeOk('table présente'));

    // Colonnes
    lineOut('<div style="margin:6px 0 2px;opacity:.9">Colonnes attendues :</div>');
    echo $isCli ? '' : '<ul style="margin:6px 0 14px;line-height:1.6">';
    foreach ($rules['columns'] as $columnName) {
        $exists = columnExists($pdo, $tableName, $columnName);
        $badge  = $exists ? badgeOk() : badgeErr('absente');
        $item   = $badge . ' <code>' . $tableName.'.'.$columnName . '</code>';
        echo $isCli ? strip_tags($item).PHP_EOL : '<li>'.$item.'</li>';
    }
    echo $isCli ? '' : '</ul>';

    // Index
    if (!empty($rules['indexes'])) {
        lineOut('<div style="margin:6px 0 2px;opacity:.9">Index attendus :</div>');
        echo $isCli ? '' : '<ul style="margin:6px 0 14px;line-height:1.6">';
        foreach ($rules['indexes'] as $indexName) {
            $exists = indexExists($pdo, $tableName, $indexName);
            $badge  = $exists ? badgeOk() : badgeWarn('non trouvé');
            $item   = $badge . ' <code>'.$indexName.'</code>';
            echo $isCli ? strip_tags($item).PHP_EOL : '<li>'.$item.'</li>';
        }
        echo $isCli ? '' : '</ul>';
    }

    // FKs
    if (!empty($rules['fks'])) {
        lineOut('<div style="margin:6px 0 2px;opacity:.9">Clés étrangères attendues :</div>');
        echo $isCli ? '' : '<ul style="margin:6px 0 14px;line-height:1.6">';
        foreach ($rules['fks'] as $from => $to) {
            $hasFk = isset($fkMap[$from]) && $fkMap[$from] === $to;
            $badge = $hasFk ? badgeOk() : badgeWarn('FK manquante');
            $item  = $badge . ' <code>'.$from.'</code> → <code>'.$to.'</code>';
            echo $isCli ? strip_tags($item).PHP_EOL : '<li>'.$item.'</li>';
        }
        echo $isCli ? '' : '</ul>';
    }
}

// 7) Comptages rapides (sanity check)
lineOut('<h2 style="margin:24px 0 6px">Comptes rapides</h2>');
$counts = [];
foreach (['users','slots','reservations'] as $t) {
    if (tableExists($pdo, $t)) {
        try {
            $counts[$t] = (int)$pdo->query('SELECT COUNT(*) FROM `'.$t.'`')->fetchColumn();
        } catch (Throwable $e) {
            $counts[$t] = -1;
        }
    }
}
if (!$isCli) echo '<ul style="margin:6px 0 14px;line-height:1.6">';
foreach ($counts as $t => $c) {
    $label = ($c >= 0) ? $c.' ligne(s)' : 'n/a';
    lineOut('<li><code>'.$t.'</code> : '.$label.'</li>');
}
if (!$isCli) echo '</ul>';

// 8) Aide
lineOut('<h2 style="margin:24px 0 6px">Aide</h2>');
lineOut('<div style="opacity:.85">Si des erreurs apparaissent : relance <code>database/integration.php</code> (ou corrige manuellement via phpMyAdmin) pour créer les colonnes/index/FK manquants.</div>');

if (!$isCli) {
    echo '<div style="margin-top:18px;opacity:.6">Pense à supprimer <code>database/check.php</code> une fois le diagnostic terminé.</div>';
    echo '</body>';
}

