<?php
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !hasPermission('admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$pdo = getDatabaseConnection();

$universite_id = isset($_GET['universite_id']) ? (int)$_GET['universite_id'] : 0;
$months = isset($_GET['months']) ? max(1, min(36, (int)$_GET['months'])) : 12; // 1..36 months

if ($universite_id <= 0) {
    echo json_encode(['labels' => [], 'datasets' => []]);
    exit;
}

// Build a list of the last N months as YYYY-MM
$labels = [];
$start = new DateTime('first day of this month');
$start->modify('-' . ($months - 1) . ' months');
$period = new DatePeriod($start, new DateInterval('P1M'), $months);
foreach ($period as $dt) {
    $labels[] = $dt->format('Y-m');
}

// Query counts per filiere per month for the specified universite
// We map etudiants by date_inscription month and filiere linked to this universite via universite_filiere
$sql = "
    SELECT f.id AS filiere_id, f.nom AS filiere_nom,
           DATE_FORMAT(e.date_inscription, '%Y-%m') AS ym,
           COUNT(e.id) AS nb
    FROM universite_filiere uf
    INNER JOIN filieres f ON f.id = uf.filiere_id
    LEFT JOIN etudiants e ON e.filiere_id = f.id
         AND e.date_inscription IS NOT NULL
         AND e.date_inscription >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL :months MONTH), '%Y-%m-01')
    WHERE uf.universite_id = :universite_id
    GROUP BY f.id, f.nom, ym
    ORDER BY f.nom, ym
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':universite_id', $universite_id, PDO::PARAM_INT);
$stmt->bindValue(':months', $months, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize data by filiere
$byFiliere = [];
$filiereNames = [];
foreach ($rows as $r) {
    $fid = (int)$r['filiere_id'];
    $byFiliere[$fid][$r['ym']] = (int)$r['nb'];
    $filiereNames[$fid] = $r['filiere_nom'];
}

// Build datasets aligned to labels (months)
$colors = [
    '#3366CC','#DC3912','#FF9900','#109618','#990099','#0099C6','#DD4477','#66AA00','#B82E2E','#316395',
    '#994499','#22AA99','#AAAA11','#6633CC','#E67300','#8B0707','#651067','#329262','#5574A6','#3B3EAC'
];
$colorCount = count($colors);
$datasets = [];
$idx = 0;
foreach ($filiereNames as $fid => $name) {
    $data = [];
    foreach ($labels as $ym) {
        $data[] = isset($byFiliere[$fid][$ym]) ? (int)$byFiliere[$fid][$ym] : 0;
    }
    $color = $colors[$idx % $colorCount];
    $datasets[] = [
        'label' => $name,
        'data' => $data,
        'borderColor' => $color,
        'backgroundColor' => $color,
        'tension' => 0.25,
        'fill' => false
    ];
    $idx++;
}

echo json_encode([
    'labels' => $labels,
    'datasets' => $datasets
]);
