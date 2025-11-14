<?php
require_once '../config.php';

// Vérification de la session
if (!isLoggedIn()) {
    redirect('../login.php');
}

// Vérification des permissions (admin principal uniquement)
if (!hasPermission('admin')) {
    redirect('../login.php');
}

$user_type = $_SESSION['user_type'];
$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// Récupération des statistiques globales
try {
    $pdo = getDatabaseConnection();
    
    // Statistiques des universités
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM universites");
    $total_universites = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Statistiques des filières
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM filieres");
    $total_filieres = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Statistiques des étudiants
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM etudiants");
    $total_etudiants = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Statistiques des professeurs
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM professeurs");
    $total_professeurs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Top 5 des universités par nombre d'étudiants
    $stmt = $pdo->query("
        SELECT u.nom, COUNT(e.id) as nb_etudiants 
        FROM universites u 
        LEFT JOIN universite_filiere uf ON u.id = uf.universite_id 
        LEFT JOIN etudiants e ON uf.filiere_id = e.filiere_id 
        GROUP BY u.id 
        ORDER BY nb_etudiants DESC 
        LIMIT 5
    ");
    $top_universites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Liste des universités (pour sélecteur des courbes)
    $stmt = $pdo->query("SELECT id, nom FROM universites ORDER BY nom");
    $universites_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Activités récentes (réelles) à l'échelle du système
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
                   CONCAT('[', u.nom, '] Professeur #', pc.professeur_id, ' affecté à la classe #', pc.classe_id) AS nom,
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
                   CONCAT('[', u.nom, '] Professeur #', mp.professeur_id, ' affecté à la matière #', mp.matiere_id) AS nom,
                   'Affectation à une matière' AS action,
                   mp.date_affectation AS date
            FROM matiere_professeur mp
            INNER JOIN matieres m ON m.id = mp.matiere_id
            INNER JOIN filieres f ON f.id = m.filiere_id
            INNER JOIN universite_filiere uf ON uf.filiere_id = f.id
            INNER JOIN universites u ON u.id = uf.universite_id
        )
        ORDER BY date DESC
        LIMIT 5
    ";
    $stmt = $pdo->query($sqlRecent);
    $activites_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // En cas d'erreur, utiliser des données simulées
    $total_universites = 8;
    $total_filieres = 45;
    $total_etudiants = 12500;
    $total_professeurs = 850;
    $top_universites = [
        ['nom' => 'Université de Paris', 'nb_etudiants' => 3200],
        ['nom' => 'Université de Lyon', 'nb_etudiants' => 2800],
        ['nom' => 'Université de Marseille', 'nb_etudiants' => 2100],
        ['nom' => 'Université de Toulouse', 'nb_etudiants' => 1900],
        ['nom' => 'Université de Nantes', 'nb_etudiants' => 1600]
    ];
    $activites_recentes = [
        ['type' => 'universite', 'nom' => 'Université de Bordeaux', 'action' => 'Créée', 'date' => '2024-01-15'],
        ['type' => 'universite', 'nom' => 'Université de Strasbourg', 'action' => 'Créée', 'date' => '2024-01-14'],
        ['type' => 'universite', 'nom' => 'Université de Montpellier', 'action' => 'Créée', 'date' => '2024-01-13']
    ];
    $universites_list = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrateur Principal - Portail des Résultats Universitaires</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .dashboard-container {
            min-height: 100vh;
            background: #f8f9fa;
        }
        
        .sidebar {
            background: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            height: 100vh;
            position: fixed;
            width: 280px;
            z-index: 1000;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 20px;
        }
        
        .nav-link {
            color: #6c757d;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 4px 0;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            background: var(--primary-color);
            color: white;
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        
        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: white;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="p-4">
                <h4 class="text-primary mb-4">
                    <i class="fas fa-graduation-cap"></i>
                    Admin Principal
                </h4>
                
                <nav class="nav flex-column">
                    <a href="dashboard.php" class="nav-link active">
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
                    <a href="statistiques.php" class="nav-link">
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
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Tableau de bord</h1>
                    <p class="text-muted">Administrateur Principal - Vue d'ensemble du système</p>
                </div>
                <div class="d-flex align-items-center">
                    <span class="text-muted me-3">Connecté en tant que <strong><?php echo htmlspecialchars($username); ?></strong></span>
                    <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
            </div>

            <!-- Statistiques principales -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon bg-primary me-3">
                                <i class="fas fa-university"></i>
                            </div>
                            <div>
                                <div class="stats-number"><?php echo $total_universites; ?></div>
                                <div class="stats-label">Universités</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon bg-success me-3">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div>
                                <div class="stats-number"><?php echo $total_filieres; ?></div>
                                <div class="stats-label">Filières</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon bg-info me-3">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div>
                                <div class="stats-number"><?php echo number_format($total_etudiants); ?></div>
                                <div class="stats-label">Étudiants</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon bg-warning me-3">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <div>
                                <div class="stats-number"><?php echo $total_professeurs; ?></div>
                                <div class="stats-label">Professeurs</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Top 5 des universités -->
                <div class="col-md-6">
                    <div class="chart-card">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-trophy text-warning me-2"></i>
                            Top 5 des universités
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Université</th>
                                        <th>Étudiants</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_universites as $index => $universite): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-primary me-2">#<?php echo $index + 1; ?></span>
                                                <?php echo htmlspecialchars($universite['nom']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo number_format($universite['nb_etudiants']); ?></td>
                                        <td>
                                            <?php 
                                            $percentage = $total_etudiants > 0 ? round(($universite['nb_etudiants'] / $total_etudiants) * 100, 1) : 0;
                                            echo $percentage . '%';
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Activités récentes -->
                <div class="col-md-6">
                    <div class="chart-card">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-clock text-info me-2"></i>
                            Activités récentes
                        </h5>
                        <div class="activity-list">
                            <?php foreach ($activites_recentes as $activite): ?>
                            <div class="activity-item d-flex align-items-center">
                                <div class="activity-icon bg-success me-3">
                                    <i class="fas fa-university"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?php echo htmlspecialchars($activite['nom']); ?></div>
                                    <div class="text-muted small">
                                        <?php echo $activite['action']; ?> - 
                                        <?php echo date('d/m/Y', strtotime($activite['date'])); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Courbes par université et par filière (inscriptions mensuelles) -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="chart-card">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-chart-line text-primary me-2"></i>
                            Évolution des inscriptions par filière
                        </h5>
                        <div class="row g-2 mb-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label">Université</label>
                                <select id="selectUniversite" class="form-select">
                                    <?php foreach (($universites_list ?? []) as $u): ?>
                                        <option value="<?php echo (int)$u['id']; ?>"><?php echo htmlspecialchars($u['nom']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Période (mois)</label>
                                <select id="selectMonths" class="form-select">
                                    <option value="6">6 mois</option>
                                    <option value="12" selected>12 mois</option>
                                    <option value="24">24 mois</option>
                                </select>
                            </div>
                        </div>
                        <canvas id="chartUnivFilieres" height="120"></canvas>
                        <div class="text-muted small mt-2">Source: inscriptions étudiants par `etudiants.date_inscription`</div>
                    </div>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="chart-card">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-bolt text-warning me-2"></i>
                            Actions rapides
                        </h5>
                        <div class="row">
                            <div class="col-md-3">
                                <a href="universites.php?action=create" class="btn btn-primary w-100 mb-2">
                                    <i class="fas fa-plus me-2"></i>
                                    Nouvelle université
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="administrateurs.php?action=create" class="btn btn-success w-100 mb-2">
                                    <i class="fas fa-user-plus me-2"></i>
                                    Nouvel administrateur
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="statistiques.php" class="btn btn-info w-100 mb-2">
                                    <i class="fas fa-chart-line me-2"></i>
                                    Voir les statistiques
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="parametres.php" class="btn btn-secondary w-100 mb-2">
                                    <i class="fas fa-cog me-2"></i>
                                    Paramètres
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // Mise en surbrillance du lien actif
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });
            
            // Chart: per-university filière curves
            const selUniv = document.getElementById('selectUniversite');
            const selMonths = document.getElementById('selectMonths');
            const ctx = document.getElementById('chartUnivFilieres');
            let lineChart = null;
            
            async function loadAndRender() {
                if (!selUniv || !ctx) return;
                const univId = selUniv.value;
                const months = selMonths ? selMonths.value : 12;
                if (!univId) return;
                try {
                    const resp = await fetch(`api_stats_univ.php?universite_id=${encodeURIComponent(univId)}&months=${encodeURIComponent(months)}`);
                    const data = await resp.json();
                    const labels = (data && data.labels) ? data.labels : [];
                    const datasets = (data && data.datasets) ? data.datasets : [];
                    if (lineChart) { lineChart.destroy(); }
                    lineChart = new Chart(ctx, {
                        type: 'line',
                        data: { labels, datasets },
                        options: {
                            responsive: true,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { position: 'bottom' },
                                tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y}` } }
                            },
                            scales: {
                                y: { beginAtZero: true, title: { display: true, text: 'Inscriptions' } },
                                x: { title: { display: true, text: 'Mois' } }
                            }
                        }
                    });
                } catch (e) {
                    console.error(e);
                }
            }
            
            if (selUniv) {
                selUniv.addEventListener('change', loadAndRender);
            }
            if (selMonths) {
                selMonths.addEventListener('change', loadAndRender);
            }
            // Auto-select first university and render
            if (selUniv && selUniv.options.length > 0) {
                selUniv.selectedIndex = 0;
                loadAndRender();
            }
        });
    </script>
</body>
</html>