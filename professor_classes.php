<?php
require_once 'config.php';

// Vérifier que l'utilisateur est connecté et est un professeur
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'professeur') {
    redirect('student_login.php');
}

$pdo = getDatabaseConnection();
$profId = (int)$_SESSION['user_id'];

// Récupérer toutes les classes assignées (via professeur_classe OU via affectations)
$classes = [];
try {
    $sql = "SELECT DISTINCT c.*, f.nom AS filiere_nom,
                (SELECT COUNT(*) FROM etudiants e WHERE e.classe_id = c.id) AS total_etudiants
            FROM classes c
            INNER JOIN filieres f ON c.filiere_id = f.id
            WHERE c.id IN (
                SELECT classe_id FROM professeur_classe WHERE professeur_id = ?
            )
            OR c.id IN (
                SELECT classe_id FROM affectations WHERE professeur_id = ?
            )
            OR c.filiere_id IN (
                SELECT mf.filiere_id
                FROM matiere_filiere mf
                WHERE mf.matiere_id IN (
                    SELECT mp.matiere_id FROM matiere_professeur mp WHERE mp.professeur_id = ?
                )
            )
            OR c.filiere_id IN (
                SELECT DISTINCT m.filiere_id
                FROM matiere_professeur mp
                INNER JOIN matieres m ON mp.matiere_id = m.id
                WHERE mp.professeur_id = ?
            )
            ORDER BY c.nom ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$profId, $profId, $profId, $profId]);
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des classes";
}

// Debug facultatif
$debugInfo = [];
if (defined('DEBUG_MODE') && DEBUG_MODE && isset($_GET['debug'])) {
    try {
        $c1 = $pdo->prepare("SELECT COUNT(*) FROM professeur_classe WHERE professeur_id = ?");
        $c1->execute([$profId]);
        $countPC = (int)$c1->fetchColumn();

        $c2 = $pdo->prepare("SELECT COUNT(DISTINCT classe_id) FROM affectations WHERE professeur_id = ?");
        $c2->execute([$profId]);
        $countAff = (int)$c2->fetchColumn();

        // Count of subjects assigned to professor
        $c3 = $pdo->prepare("SELECT COUNT(*) FROM matiere_professeur WHERE professeur_id = ?");
        $c3->execute([$profId]);
        $countMP = (int)$c3->fetchColumn();

        // Filiere IDs linked to professor via matiere_professeur -> matieres.filiere_id
        $qF = $pdo->prepare("SELECT DISTINCT m.filiere_id FROM matiere_professeur mp INNER JOIN matieres m ON mp.matiere_id = m.id WHERE mp.professeur_id = ?");
        $qF->execute([$profId]);
        $filiereIds = $qF->fetchAll(PDO::FETCH_COLUMN);

        // Classes count via filieres from MP route
        $classesViaFil = 0;
        if (!empty($filiereIds)) {
            $in = implode(',', array_fill(0, count($filiereIds), '?'));
            $qC = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE filiere_id IN ($in)");
            $qC->execute($filiereIds);
            $classesViaFil = (int)$qC->fetchColumn();
        }

        $debugInfo = [
            'session_user_id' => $profId,
            'session_user_type' => $_SESSION['user_type'] ?? null,
            'professeur_classe_count' => $countPC,
            'affectations_classes_count' => $countAff,
            'matiere_professeur_count' => $countMP,
            'filiere_ids_from_mp' => $filiereIds,
            'classes_count_via_filiere_from_mp' => $classesViaFil,
        ];
    } catch (Exception $e) {
        $debugInfo['error'] = $e->getMessage();
    }
}

function matieresForClass(PDO $pdo, int $profId, int $classeId): array {
    // Récupérer filiere de la classe
    $stF = $pdo->prepare("SELECT filiere_id FROM classes WHERE id = ?");
    $stF->execute([$classeId]);
    $filiereId = (int)$stF->fetchColumn();

    $sql = "SELECT DISTINCT m.id, m.nom, m.code
            FROM matieres m
            WHERE m.id IN (
                SELECT a.matiere_id FROM affectations a WHERE a.professeur_id = ? AND a.classe_id = ?
            )
            OR (
                m.id IN (SELECT mp.matiere_id FROM matiere_professeur mp WHERE mp.professeur_id = ?)
                AND (
                    m.filiere_id = ?
                    OR EXISTS (
                        SELECT 1 FROM matiere_filiere mf WHERE mf.matiere_id = m.id AND mf.filiere_id = ?
                    )
                )
            )
            ORDER BY m.nom";
    $st = $pdo->prepare($sql);
    $st->execute([$profId, $classeId, $profId, $filiereId, $filiereId]);
    return $st->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Classes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="professor_dashboard.php">⟵ Tableau de bord</a>
        <span class="navbar-text">Mes Classes</span>
    </div>
</nav>
<div class="container py-4">
    <h3 class="mb-3">Mes Classes</h3>

    <?php if (!empty($debugInfo)): ?>
        <div class="alert alert-warning">
            <strong>Debug:</strong>
            <pre class="mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars(json_encode($debugInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($classes)): ?>
        <div class="alert alert-info">Aucune classe assignée pour le moment.</div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($classes as $c): ?>
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title mb-1"><?= htmlspecialchars($c['nom']) ?></h5>
                                    <small class="text-muted">Filière: <?= htmlspecialchars($c['filiere_nom']) ?></small>
                                </div>
                                <span class="badge bg-primary"><?= (int)$c['total_etudiants'] ?> étudiants</span>
                            </div>
                            <hr>
                            <div>
                                <strong>Matières enseignées:</strong>
                                <?php $mats = matieresForClass($pdo, $profId, (int)$c['id']); ?>
                                <?php if (empty($mats)): ?>
                                    <div class="text-muted">Aucune matière affectée dans cette classe.</div>
                                <?php else: ?>
                                    <div>
                                        <?php foreach ($mats as $m): ?>
                                            <span class="badge bg-secondary me-1 mb-1">
                                                <?= htmlspecialchars($m['nom']) ?><?= $m['code'] ? ' ('.htmlspecialchars($m['code']).')' : '' ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-white d-flex gap-2">
                            <a href="professor_grades.php?classe_id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-success">Saisir des notes</a>
                            <a href="professor_reports.php?classe_id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-info">Voir rapports</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
