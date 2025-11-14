<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'professeur') {
    redirect('student_login.php');
}

$pdo = getDatabaseConnection();
$profId = (int)$_SESSION['user_id'];
$year = DEFAULT_ACADEMIC_YEAR;

// KPIs
$nbClasses = $pdo->prepare("SELECT COUNT(*) FROM professeur_classe WHERE professeur_id=?");
$nbClasses->execute([$profId]);
$kpiClasses = (int)$nbClasses->fetchColumn();

$nbEtudiants = $pdo->prepare("SELECT COUNT(*) FROM etudiants e WHERE e.classe_id IN (SELECT classe_id FROM professeur_classe WHERE professeur_id=?)");
$nbEtudiants->execute([$profId]);
$kpiEtudiants = (int)$nbEtudiants->fetchColumn();

$nbNotes = $pdo->prepare("SELECT COUNT(*) FROM notes n WHERE n.matiere_id IN (SELECT matiere_id FROM affectations WHERE professeur_id=?) AND annee_academique=?");
$nbNotes->execute([$profId, $year]);
$kpiNotes = (int)$nbNotes->fetchColumn();

// Moyennes par matière (pondérées 40% classe, 60% examen)
$avgMatieres = [];
try {
    // Récupérer toutes les notes (classe/examen) des matières de ce prof pour l'année
    $sql = "SELECT n.matiere_id, n.etudiant_id, n.type_note, n.note, m.nom, m.code
            FROM notes n
            INNER JOIN matieres m ON n.matiere_id = m.id
            WHERE n.matiere_id IN (SELECT matiere_id FROM affectations WHERE professeur_id=?)
              AND n.annee_academique = ?";
    $st = $pdo->prepare($sql);
    $st->execute([$profId, $year]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // Agréger par matière puis par étudiant
    $perMatiere = [];
    $matiereInfo = [];
    foreach ($rows as $r) {
        $mid = (int)$r['matiere_id'];
        $eid = (int)$r['etudiant_id'];
        $matiereInfo[$mid] = ['nom' => $r['nom'], 'code' => $r['code']];
        if (!isset($perMatiere[$mid])) $perMatiere[$mid] = [];
        if (!isset($perMatiere[$mid][$eid])) $perMatiere[$mid][$eid] = ['classe' => null, 'examen' => null];
        $t = isset($r['type_note']) ? $r['type_note'] : null;
        // Si ancien schéma sans type_note, on ne peut pas pondérer correctement; on prend la note telle quelle comme moyenne
        if ($t === null) {
            $perMatiere[$mid][$eid]['classe'] = (float)$r['note'];
        } else if ($t === 'classe' || $t === 'examen') {
            $perMatiere[$mid][$eid][$t] = (float)$r['note'];
        }
    }

    // Calcul des moyennes pondérées par matière
    $avgMatieres = [];
    foreach ($perMatiere as $mid => $etuds) {
        $sum = 0.0; $cnt = 0;
        foreach ($etuds as $eid => $vals) {
            $classe = $vals['classe'];
            $examen = $vals['examen'];
            if ($classe !== null && $examen !== null) {
                $avg = 0.4 * $classe + 0.6 * $examen;
            } else if ($classe !== null) {
                $avg = $classe;
            } else if ($examen !== null) {
                $avg = $examen;
            } else {
                continue;
            }
            $sum += $avg; $cnt++;
        }
        if ($cnt > 0) {
            $avgMatieres[] = [
                'nom' => $matiereInfo[$mid]['nom'] ?? '',
                'code' => $matiereInfo[$mid]['code'] ?? '',
                'moyenne' => $sum / $cnt,
            ];
        }
    }

    // Trier par nom de matière pour l'affichage
    usort($avgMatieres, function($a, $b) {
        return strcmp($a['nom'], $b['nom']);
    });
} catch (PDOException $e) {
    // En cas d'erreur SQL, laisser la section vide sans casser la page
    $avgMatieres = [];
}

// Dernières notes
$recentNotes = [];
$sr = $pdo->prepare("SELECT n.note, e.nom, e.prenom, m.nom AS matiere_nom
    FROM notes n
    INNER JOIN etudiants e ON n.etudiant_id=e.id
    INNER JOIN matieres m ON n.matiere_id=m.id
    WHERE n.matiere_id IN (SELECT matiere_id FROM affectations WHERE professeur_id=?)
    ORDER BY n.id DESC
    LIMIT 10");
$sr->execute([$profId]);
$recentNotes = $sr->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="professor_dashboard.php">⟵ Tableau de bord</a>
        <span class="navbar-text">Rapports</span>
    </div>
</nav>
<div class="container py-4">
    <h3 class="mb-3">Rapports d'Enseignement</h3>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <div class="display-6"><?= $kpiClasses ?></div>
                    <div class="text-muted">Classes</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <div class="display-6"><?= $kpiEtudiants ?></div>
                    <div class="text-muted">Étudiants</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <div class="display-6"><?= $kpiNotes ?></div>
                    <div class="text-muted">Notes saisies (<?= htmlspecialchars($year) ?>)</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white">Moyenne par matière (<?= htmlspecialchars($year) ?>)</div>
        <div class="card-body">
            <?php if (empty($avgMatieres)): ?>
                <div class="text-muted">Aucune note saisie.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Matière</th>
                                <th>Moyenne</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($avgMatieres as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['nom']) ?><?= $row['code']?' ('.htmlspecialchars($row['code']).')':'' ?></td>
                                    <td><?= number_format((float)$row['moyenne'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white">Dernières notes saisies</div>
        <div class="card-body">
            <?php if (empty($recentNotes)): ?>
                <div class="text-muted">Aucune note récente.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($recentNotes as $n): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>
                                <strong><?= htmlspecialchars($n['prenom'].' '.$n['nom']) ?></strong>
                                — <?= htmlspecialchars($n['matiere_nom']) ?>
                            </span>
                            <span class="badge bg-success"><?= formatNote($n['note']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
