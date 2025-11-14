<?php
require_once '../config.php';

// Auth checks
if (!isLoggedIn()) {
    redirect('../login.php');
}
if (!hasPermission('admin')) {
    redirect('../login.php');
}

$username = $_SESSION['username'] ?? 'Admin';

$errors = [];

try {
    $pdo = getDatabaseConnection();

    // Totals
    $total_universites = (int)$pdo->query("SELECT COUNT(*) AS c FROM universites")->fetch(PDO::FETCH_ASSOC)['c'];
    $total_filieres    = (int)$pdo->query("SELECT COUNT(*) AS c FROM filieres")->fetch(PDO::FETCH_ASSOC)['c'];
    $total_classes     = (int)$pdo->query("SELECT COUNT(*) AS c FROM classes")->fetch(PDO::FETCH_ASSOC)['c'];
    $total_etudiants   = (int)$pdo->query("SELECT COUNT(*) AS c FROM etudiants")->fetch(PDO::FETCH_ASSOC)['c'];
    $total_professeurs = (int)$pdo->query("SELECT COUNT(*) AS c FROM professeurs")->fetch(PDO::FETCH_ASSOC)['c'];
    $total_matieres    = (int)$pdo->query("SELECT COUNT(*) AS c FROM matieres")->fetch(PDO::FETCH_ASSOC)['c'];

    // Universités: filières, classes, étudiants, professeurs
    $stmt = $pdo->query(
        "SELECT 
            u.id,
            u.nom,
            COUNT(DISTINCT uf.filiere_id)        AS nb_filieres,
            COUNT(DISTINCT c.id)                 AS nb_classes,
            COUNT(DISTINCT e.id)                 AS nb_etudiants,
            COUNT(DISTINCT a.professeur_id)      AS nb_professeurs
         FROM universites u
         LEFT JOIN universite_filiere uf ON uf.universite_id = u.id
         LEFT JOIN filieres f           ON f.id = uf.filiere_id
         LEFT JOIN classes c            ON c.filiere_id = f.id
         LEFT JOIN etudiants e          ON e.filiere_id = f.id
         LEFT JOIN affectations a       ON a.classe_id = c.id
         GROUP BY u.id, u.nom
         ORDER BY u.nom"
    );
    $stats_universites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Étudiants par filière
    $stmt = $pdo->query(
        "SELECT f.id, f.nom AS filiere_nom, COUNT(e.id) AS nb_etudiants
         FROM filieres f
         LEFT JOIN etudiants e ON e.filiere_id = f.id
         GROUP BY f.id, f.nom
         ORDER BY nb_etudiants DESC, f.nom"
    );
    $etudiants_par_filiere = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Étudiants par classe (top 10)
    $stmt = $pdo->query(
        "SELECT c.id, c.nom AS classe_nom, COUNT(e.id) AS nb_etudiants
         FROM classes c
         LEFT JOIN etudiants e ON e.classe_id = c.id
         GROUP BY c.id, c.nom
         ORDER BY nb_etudiants DESC, c.nom
         LIMIT 10"
    );
    $etudiants_par_classe_top = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Moyennes des notes par matière (sur l'ensemble)
    $stmt = $pdo->query(
        "SELECT m.id, m.nom AS matiere_nom,
                COUNT(n.id) AS nb_notes,
                ROUND(AVG(n.note), 2) AS moyenne
         FROM matieres m
         LEFT JOIN notes n ON n.matiere_id = m.id
         GROUP BY m.id, m.nom
         HAVING nb_notes > 0
         ORDER BY moyenne DESC, nb_notes DESC, m.nom
         LIMIT 10"
    );
    $top_matieres_par_moyenne = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Répartition des notes (par tranches) globale
    $stmt = $pdo->query(
        "SELECT 
            SUM(CASE WHEN note < 5 THEN 1 ELSE 0 END)        AS n_lt5,
            SUM(CASE WHEN note >= 5 AND note < 10 THEN 1 ELSE 0 END) AS n_5_10,
            SUM(CASE WHEN note >= 10 AND note < 15 THEN 1 ELSE 0 END) AS n_10_15,
            SUM(CASE WHEN note >= 15 THEN 1 ELSE 0 END)      AS n_ge15,
            COUNT(*) AS n_total
         FROM notes"
    );
    $repartition_notes = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['n_lt5'=>0,'n_5_10'=>0,'n_10_15'=>0,'n_ge15'=>0,'n_total'=>0];

    // Activités récentes (réelles) système
    $sqlRecent = "
        (
            SELECT 'matiere' AS type,
                   CONCAT('[', u.nom, '] ', m.nom) AS nom,
                   'Matière créée' AS action,
                   m.date_creation AS date
            FROM matieres m
            INNER JOIN filieres f ON f.id = m.filiere_id
            INNER JOIN universite_filiere uf ON uf.filiere_id = f.id
            INNER JOIN universites u ON u.id = uf.universite_id
        )
        UNION ALL
        (
            SELECT 'etudiant' AS type,
                   CONCAT('[', u.nom, '] ', e.prenom, ' ', e.nom) AS nom,
                   'Étudiant inscrit' AS action,
                   e.date_inscription AS date
            FROM etudiants e
            INNER JOIN filieres f ON f.id = e.filiere_id
            INNER JOIN universite_filiere uf ON uf.filiere_id = f.id
            INNER JOIN universites u ON u.id = uf.universite_id
        )
        UNION ALL
        (
            SELECT 'affectation_classe' AS type,
                   CONCAT('[', u.nom, '] Professeur #', pc.professeur_id, ' -> Classe #', pc.classe_id) AS nom,
                   'Affectation à une classe' AS action,
                   pc.date_affectation AS date
            FROM professeur_classe pc
            INNER JOIN classes c ON c.id = pc.classe_id
            INNER JOIN filieres f ON f.id = c.filiere_id
            INNER JOIN universite_filiere uf ON uf.filiere_id = f.id
            INNER JOIN universites u ON u.id = uf.universite_id
        )
        UNION ALL
        (
            SELECT 'affectation_matiere' AS type,
                   CONCAT('[', u.nom, '] Professeur #', mp.professeur_id, ' -> Matière #', mp.matiere_id) AS nom,
                   'Affectation à une matière' AS action,
                   mp.date_affectation AS date
            FROM matiere_professeur mp
            INNER JOIN matieres m ON m.id = mp.matiere_id
            INNER JOIN filieres f ON f.id = m.filiere_id
            INNER JOIN universite_filiere uf ON uf.filiere_id = f.id
            INNER JOIN universites u ON u.id = uf.universite_id
        )
        ORDER BY date DESC
        LIMIT 8
    ";
    $activites_recentes = $pdo->query($sqlRecent)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $errors[] = $e->getMessage();
    // Initialize safe defaults
    $total_universites = $total_filieres = $total_classes = $total_etudiants = $total_professeurs = $total_matieres = 0;
    $stats_universites = $etudiants_par_filiere = $etudiants_par_classe_top = $top_matieres_par_moyenne = $activites_recentes = [];
    $repartition_notes = ['n_lt5'=>0,'n_5_10'=>0,'n_10_15'=>0,'n_ge15'=>0,'n_total'=>0];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques - Admin Principal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .dashboard-container { min-height: 100vh; background:#f8f9fa; }
        .sidebar { background:white; box-shadow:2px 0 10px rgba(0,0,0,.1); height:100vh; position:fixed; width:280px; z-index:1000; }
        .main-content { margin-left:280px; padding:20px; }
        .stats-card { background:white; border-radius:15px; padding:20px; box-shadow:0 5px 15px rgba(0,0,0,.08); }
        .stats-icon { width:50px; height:50px; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; }
        .chart-card { background:white; border-radius:15px; padding:20px; box-shadow:0 5px 15px rgba(0,0,0,.08); margin-bottom:20px; }
        .activity-item { padding:10px 0; border-bottom:1px solid #f1f3f4; }
        .activity-item:last-child { border-bottom:none; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-4">
            <h4 class="text-primary mb-4">
                <i class="fas fa-chart-bar"></i>
                Statistiques
            </h4>
            <nav class="nav flex-column">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    Tableau de bord
                </a>
                <a href="universites.php" class="nav-link">
                    <i class="fas fa-university"></i>
                    Gérer les universités
                </a>
                <a href="administrateurs.php" class="nav-link">
                    <i class="fas fa-users-cog"></i>
                    Gérer les administrateurs
                </a>
                <a href="statistiques.php" class="nav-link active">
                    <i class="fas fa-chart-bar"></i>
                    Statistiques globales
                </a>
                <a href="parametres.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    Paramètres système
                </a>
                <hr>
                <a href="../logout.php" class="nav-link text-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Statistiques globales</h1>
                <p class="text-muted">Vue d'ensemble du système - Administrateur Principal</p>
            </div>
            <div class="d-flex align-items-center">
                <a href="dashboard.php" class="btn btn-outline-secondary me-3">
                    <i class="fas fa-arrow-left me-1"></i> Retour au Dashboard
                </a>
                <div class="text-muted">Connecté en tant que <strong><?php echo htmlspecialchars($username); ?></strong></div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars(implode(' | ', $errors)); ?></div>
        <?php endif; ?>

        <!-- Top stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <div class="stats-card text-center">
                    <div class="stats-icon bg-primary mx-auto mb-2"><i class="fas fa-university"></i></div>
                    <div class="fs-4 fw-bold"><?php echo $total_universites; ?></div>
                    <div class="text-muted small">Universités</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card text-center">
                    <div class="stats-icon bg-success mx-auto mb-2"><i class="fas fa-graduation-cap"></i></div>
                    <div class="fs-4 fw-bold"><?php echo $total_filieres; ?></div>
                    <div class="text-muted small">Filières</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card text-center">
                    <div class="stats-icon bg-info mx-auto mb-2"><i class="fas fa-users"></i></div>
                    <div class="fs-4 fw-bold"><?php echo $total_classes; ?></div>
                    <div class="text-muted small">Classes</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card text-center">
                    <div class="stats-icon bg-warning mx-auto mb-2"><i class="fas fa-user-graduate"></i></div>
                    <div class="fs-4 fw-bold"><?php echo $total_etudiants; ?></div>
                    <div class="text-muted small">Étudiants</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card text-center">
                    <div class="stats-icon bg-danger mx-auto mb-2"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="fs-4 fw-bold"><?php echo $total_professeurs; ?></div>
                    <div class="text-muted small">Professeurs</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card text-center">
                    <div class="stats-icon bg-secondary mx-auto mb-2"><i class="fas fa-book"></i></div>
                    <div class="fs-4 fw-bold"><?php echo $total_matieres; ?></div>
                    <div class="text-muted small">Matières</div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="chart-card mb-4">
                    <h5 class="mb-3"><i class="fas fa-university text-primary me-2"></i>Universités - Synthèse</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Université</th>
                                    <th>Filières</th>
                                    <th>Classes</th>
                                    <th>Étudiants</th>
                                    <th>Professeurs</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($stats_universites as $u): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u['nom']); ?></td>
                                    <td><?php echo (int)$u['nb_filieres']; ?></td>
                                    <td><?php echo (int)$u['nb_classes']; ?></td>
                                    <td><?php echo number_format((int)$u['nb_etudiants']); ?></td>
                                    <td><?php echo (int)$u['nb_professeurs']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="chart-card mb-4">
                    <h5 class="mb-3"><i class="fas fa-signal text-info me-2"></i>Répartition des notes (globale)</h5>
                    <div class="row text-center">
                        <div class="col-6 col-md-3 mb-2">
                            <div class="p-3 bg-light rounded">
                                <div class="fw-bold">&lt; 5</div>
                                <div class="display-6"><?php echo (int)$repartition_notes['n_lt5']; ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-2">
                            <div class="p-3 bg-light rounded">
                                <div class="fw-bold">[5 - 10)</div>
                                <div class="display-6"><?php echo (int)$repartition_notes['n_5_10']; ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-2">
                            <div class="p-3 bg-light rounded">
                                <div class="fw-bold">[10 - 15)</div>
                                <div class="display-6"><?php echo (int)$repartition_notes['n_10_15']; ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-2">
                            <div class="p-3 bg-light rounded">
                                <div class="fw-bold">≥ 15</div>
                                <div class="display-6"><?php echo (int)$repartition_notes['n_ge15']; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="text-muted small mt-2">Total des notes: <?php echo number_format((int)$repartition_notes['n_total']); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="chart-card mb-4">
                    <h5 class="mb-3"><i class="fas fa-layer-group text-success me-2"></i>Top 10 matières (moyenne)</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>Matière</th>
                                    <th class="text-end">Moyenne</th>
                                    <th class="text-end"># Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($top_matieres_par_moyenne as $m): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($m['matiere_nom']); ?></td>
                                    <td class="text-end"><?php echo number_format((float)$m['moyenne'], 2); ?></td>
                                    <td class="text-end"><?php echo (int)$m['nb_notes']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="chart-card mb-4">
                    <h5 class="mb-3"><i class="fas fa-users text-warning me-2"></i>Étudiants par filière</h5>
                    <div class="table-responsive" style="max-height: 300px; overflow:auto;">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Filière</th>
                                    <th class="text-end">Étudiants</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($etudiants_par_filiere as $f): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($f['filiere_nom']); ?></td>
                                    <td class="text-end"><?php echo number_format((int)$f['nb_etudiants']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="chart-card">
                    <h5 class="mb-3"><i class="fas fa-clock text-secondary me-2"></i>Activités récentes</h5>
                    <div>
                        <?php foreach ($activites_recentes as $a): ?>
                        <div class="activity-item d-flex justify-content-between">
                            <div>
                                <div class="fw-semibold"><?php echo htmlspecialchars($a['action']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($a['nom']); ?></div>
                            </div>
                            <div class="text-muted small"><?php echo $a['date'] ? date('d/m/Y', strtotime($a['date'])) : '—'; ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($activites_recentes)): ?>
                            <div class="text-muted">Aucune activité récente.</div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <div class="chart-card">
            <h5 class="mb-3"><i class="fas fa-school text-info me-2"></i>Top 10 classes par effectif</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Classe</th>
                            <th class="text-end">Étudiants</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($etudiants_par_classe_top as $c): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($c['classe_nom']); ?></td>
                            <td class="text-end"><?php echo number_format((int)$c['nb_etudiants']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Highlight active link
(function(){
  const current = 'statistiques.php';
  document.querySelectorAll('.nav-link').forEach(a => { if (a.getAttribute('href') === current) a.classList.add('active'); });
})();
</script>
</body>
</html>
