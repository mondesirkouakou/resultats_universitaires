<?php
require_once '../config.php';

// Vérification de la session
if (!isLoggedIn()) {
    redirect('../login.php');
}

// Vérification des permissions (universite uniquement)
if (!hasPermission('universite')) {
    redirect('../login.php');
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$username = $_SESSION['username'] ?? ($_SESSION['user_email'] ?? 'Université');

try {
    $pdo = getDatabaseConnection();

    // Infos université pour l'entête
    $stmt = $pdo->prepare("SELECT id, nom, code, logo_path, slogan FROM universites WHERE id = ?");
    $stmt->execute([$user_id]);
    $universite_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Statistiques par professeur (portée: université)
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.nom,
            p.prenom,
            COUNT(DISTINCT a.classe_id) AS classes_count,
            COUNT(DISTINCT e.id) AS students_count,
            COUNT(n.id) AS notes_count,
            ROUND(AVG(n.note), 2) AS avg_note,
            ROUND(100 * AVG(CASE WHEN n.note >= 10 THEN 1 ELSE 0 END), 1) AS pass_rate
        FROM professeurs p
        INNER JOIN affectations a ON a.professeur_id = p.id
        INNER JOIN classes c ON c.id = a.classe_id
        INNER JOIN universite_filiere uf ON uf.filiere_id = c.filiere_id AND uf.universite_id = ?
        LEFT JOIN etudiants e ON e.classe_id = c.id
        LEFT JOIN notes n ON n.etudiant_id = e.id AND n.matiere_id = a.matiere_id
        GROUP BY p.id, p.nom, p.prenom
        ORDER BY p.nom, p.prenom
    ");
    $stmt->execute([$user_id]);
    $stats_professeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statistiques par classe (portée: université)
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.nom AS classe_nom,
            f.nom AS filiere_nom,
            COUNT(DISTINCT e.id) AS students_count,
            COUNT(n.id) AS notes_count,
            ROUND(AVG(n.note), 2) AS avg_note,
            ROUND(100 * AVG(CASE WHEN n.note >= 10 THEN 1 ELSE 0 END), 1) AS pass_rate
        FROM classes c
        INNER JOIN filieres f ON f.id = c.filiere_id
        INNER JOIN universite_filiere uf ON uf.filiere_id = c.filiere_id AND uf.universite_id = ?
        LEFT JOIN etudiants e ON e.classe_id = c.id
        LEFT JOIN notes n ON n.etudiant_id = e.id
        GROUP BY c.id, c.nom, f.nom
        ORDER BY f.nom, c.nom
    ");
    $stmt->execute([$user_id]);
    $stats_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statistiques par filière (portée: université)
    $stmt = $pdo->prepare("
        SELECT 
            f.id,
            f.nom AS filiere_nom,
            COUNT(DISTINCT c.id) AS classes_count,
            COUNT(DISTINCT e.id) AS students_count,
            COUNT(n.id) AS notes_count,
            ROUND(AVG(n.note), 2) AS avg_note,
            ROUND(100 * AVG(CASE WHEN n.note >= 10 THEN 1 ELSE 0 END), 1) AS pass_rate
        FROM filieres f
        INNER JOIN universite_filiere uf ON uf.filiere_id = f.id AND uf.universite_id = ?
        LEFT JOIN classes c ON c.filiere_id = f.id
        LEFT JOIN etudiants e ON e.filiere_id = f.id
        LEFT JOIN notes n ON n.etudiant_id = e.id
        GROUP BY f.id, f.nom
        ORDER BY f.nom
    ");
    $stmt->execute([$user_id]);
    $stats_filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = $e->getMessage();
    $stats_professeurs = $stats_professeurs ?? [];
    $stats_classes = $stats_classes ?? [];
    $stats_filieres = $stats_filieres ?? [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques des résultats - Portail des Résultats Universitaires</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="dashboard-container d-flex">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-4">
            <div class="text-center mb-4">
                <i class="fas fa-university fa-3x text-primary mb-3"></i>
                <h5 class="mb-0">Dashboard Université</h5>
                <small class="text-muted">Administration</small>
            </div>
            <nav class="nav flex-column">
                <a class="nav-link" href="universite_dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a>
                <a class="nav-link" href="filieres.php"><i class="fas fa-graduation-cap"></i> Gérer les filières</a>
                <a class="nav-link" href="matieres.php"><i class="fas fa-book"></i> Gérer les matières</a>
                <a class="nav-link" href="classes.php"><i class="fas fa-users"></i> Gérer les classes</a>
                <a class="nav-link" href="etudiants.php"><i class="fas fa-user-graduate"></i> Gérer les étudiants</a>
                <a class="nav-link" href="professeurs.php"><i class="fas fa-chalkboard-teacher"></i> Gérer les professeurs</a>
                <a class="nav-link" href="affectations.php"><i class="fas fa-link"></i> Gérer les affectations</a>
                <a class="nav-link active" href="statistiques_resultats.php"><i class="fas fa-chart-line"></i> Statistiques des résultats</a>
                <hr>
                <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content flex-grow-1">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <?php if (!empty($universite_data['logo_path'])): ?>
                    <img src="<?php echo '../' . htmlspecialchars($universite_data['logo_path']); ?>" alt="Logo Université" style="height:48px; width:auto; object-fit:contain; margin-right:12px;">
                <?php else: ?>
                    <i class="fas fa-university fa-2x text-primary me-3"></i>
                <?php endif; ?>
                <div>
                    <h2 class="mb-1">Statistiques des résultats</h2>
                    <p class="text-muted mb-0">Bienvenue, <?php echo htmlspecialchars($username); ?></p>
                </div>
            </div>
            <div class="text-end">
                <div class="fw-bold"><?php echo htmlspecialchars($universite_data['nom'] ?? ''); ?></div>
                <small class="text-muted d-block"><?php echo htmlspecialchars($universite_data['code'] ?? ''); ?></small>
                <?php if (!empty($universite_data['slogan'])): ?>
                    <small class="text-muted fst-italic"><?php echo htmlspecialchars($universite_data['slogan']); ?></small>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Vue d'ensemble</h5>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs" id="statsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="prof-tab" data-bs-toggle="tab" data-bs-target="#tab-prof" type="button" role="tab">Par professeur</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="classe-tab" data-bs-toggle="tab" data-bs-target="#tab-classe" type="button" role="tab">Par classe</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="filiere-tab" data-bs-toggle="tab" data-bs-target="#tab-filiere" type="button" role="tab">Par filière</button>
                    </li>
                </ul>
                <div class="tab-content pt-3">
                    <div class="tab-pane fade show active" id="tab-prof" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Professeur</th>
                                        <th>Classes</th>
                                        <th>Étudiants</th>
                                        <th>Notes</th>
                                        <th>Moyenne</th>
                                        <th>Taux de réussite</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($stats_professeurs)): foreach ($stats_professeurs as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['nom'] . ' ' . $row['prenom']); ?></td>
                                        <td><?php echo (int)$row['classes_count']; ?></td>
                                        <td><?php echo (int)$row['students_count']; ?></td>
                                        <td><?php echo (int)$row['notes_count']; ?></td>
                                        <td><?php echo $row['avg_note'] !== null ? number_format((float)$row['avg_note'], 2, ',', ' ') : '—'; ?></td>
                                        <td><?php echo $row['pass_rate'] !== null ? number_format((float)$row['pass_rate'], 1, ',', ' ') . '%' : '—'; ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="6" class="text-muted">Aucune donnée disponible.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-classe" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Classe</th>
                                        <th>Filière</th>
                                        <th>Étudiants</th>
                                        <th>Notes</th>
                                        <th>Moyenne</th>
                                        <th>Taux de réussite</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($stats_classes)): foreach ($stats_classes as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['classe_nom']); ?></td>
                                        <td><?php echo htmlspecialchars($row['filiere_nom']); ?></td>
                                        <td><?php echo (int)$row['students_count']; ?></td>
                                        <td><?php echo (int)$row['notes_count']; ?></td>
                                        <td><?php echo $row['avg_note'] !== null ? number_format((float)$row['avg_note'], 2, ',', ' ') : '—'; ?></td>
                                        <td><?php echo $row['pass_rate'] !== null ? number_format((float)$row['pass_rate'], 1, ',', ' ') . '%' : '—'; ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="6" class="text-muted">Aucune donnée disponible.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-filiere" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Filière</th>
                                        <th>Classes</th>
                                        <th>Étudiants</th>
                                        <th>Notes</th>
                                        <th>Moyenne</th>
                                        <th>Taux de réussite</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($stats_filieres)): foreach ($stats_filieres as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['filiere_nom']); ?></td>
                                        <td><?php echo (int)$row['classes_count']; ?></td>
                                        <td><?php echo (int)$row['students_count']; ?></td>
                                        <td><?php echo (int)$row['notes_count']; ?></td>
                                        <td><?php echo $row['avg_note'] !== null ? number_format((float)$row['avg_note'], 2, ',', ' ') : '—'; ?></td>
                                        <td><?php echo $row['pass_rate'] !== null ? number_format((float)$row['pass_rate'], 1, ',', ' ') . '%' : '—'; ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="6" class="text-muted">Aucune donnée disponible.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Highlight active nav item
  const currentPage = window.location.pathname.split('/').pop();
  const navLinks = document.querySelectorAll('.nav-link');
  navLinks.forEach(link => { if (link.getAttribute('href') === currentPage) link.classList.add('active'); });
</script>
</body>
</html>
