<?php
require_once '../config.php';

// Vérification de la session - seul l'admin principal peut accéder
if (!isLoggedIn() || $_SESSION['user_type'] !== 'admin_principal') {
    redirect('../login.php');
}

$pdo = getDatabaseConnection();

// Récupération des statistiques
try {
    // Statistiques des universités
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM universites");
    $total_universites = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Statistiques des UFR
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM ufr");
    $total_ufr = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Statistiques des filières
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM filieres");
    $total_filieres = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Statistiques des étudiants
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM etudiants");
    $total_etudiants = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Statistiques des professeurs
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM professeurs");
    $total_professeurs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Statistiques des matières
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM matieres");
    $total_matieres = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Statistiques des classes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM classes");
    $total_classes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Récupération des universités récentes
    $stmt = $pdo->query("SELECT * FROM universites ORDER BY date_creation DESC LIMIT 5");
    $universites_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupération des activités récentes
    $stmt = $pdo->query("SELECT * FROM logs_activite ORDER BY date_creation DESC LIMIT 10");
    $activites_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des statistiques.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin Principal - Portail des Résultats Universitaires</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-container {
            min-height: 100vh;
            background: #f8f9fa;
            padding: 20px;
        }
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            height: 100%;
            border: none;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 15px;
        }
        .management-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            height: 100%;
            border: none;
            text-align: center;
        }
        .management-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: white;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-crown text-warning"></i> Dashboard Admin Principal</h2>
                <p class="text-muted">Administration principale du système universitaire</p>
            </div>
            <div>
                <a href="../logout.php" class="btn btn-outline-danger">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="stats-icon bg-primary">
                        <i class="fas fa-university"></i>
                    </div>
                    <h3><?php echo $total_universites ?? 0; ?></h3>
                    <p class="text-muted mb-0">Universités</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="stats-icon bg-success">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3><?php echo $total_etudiants ?? 0; ?></h3>
                    <p class="text-muted mb-0">Étudiants</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="stats-icon bg-warning">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <h3><?php echo $total_professeurs ?? 0; ?></h3>
                    <p class="text-muted mb-0">Professeurs</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="stats-icon bg-info">
                        <i class="fas fa-sitemap"></i>
                    </div>
                    <h3><?php echo $total_filieres ?? 0; ?></h3>
                    <p class="text-muted mb-0">Filières</p>
                </div>
            </div>
        </div>

        <!-- Gestion des universités -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-cogs me-2"></i>
                            Gestion des Universités
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="management-card">
                                    <i class="fas fa-university fa-2x text-primary mb-3"></i>
                                    <h6>Gérer les universités</h6>
                                    <p class="text-muted small">Créer, modifier et supprimer les universités</p>
                                    <a href="universites.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-cog"></i> Gérer
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="management-card">
                                    <i class="fas fa-chart-line fa-2x text-success mb-3"></i>
                                    <h6>Statistiques globales</h6>
                                    <p class="text-muted small">Voir les statistiques de toutes les universités</p>
                                    <a href="statistiques.php" class="btn btn-success btn-sm">
                                        <i class="fas fa-chart-bar"></i> Voir
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="management-card">
                                    <i class="fas fa-users fa-2x text-warning mb-3"></i>
                                    <h6>Gestion des comptes</h6>
                                    <p class="text-muted small">Gérer les comptes administrateurs</p>
                                    <a href="comptes.php" class="btn btn-warning btn-sm">
                                        <i class="fas fa-user-cog"></i> Gérer
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="management-card">
                                    <i class="fas fa-shield-alt fa-2x text-danger mb-3"></i>
                                    <h6>Sécurité système</h6>
                                    <p class="text-muted small">Paramètres de sécurité et logs</p>
                                    <a href="securite.php" class="btn btn-danger btn-sm">
                                        <i class="fas fa-lock"></i> Configurer
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-university me-2"></i>
                            Universités récentes
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($universites_recentes)): ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-university fa-2x mb-2"></i>
                                <p>Aucune université enregistrée.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($universites_recentes as $universite): ?>
                                <div class="activity-item">
                                    <div class="d-flex align-items-center">
                                        <div class="activity-icon bg-primary me-3">
                                            <i class="fas fa-university"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($universite['nom']); ?></h6>
                                            <small class="text-muted">
                                                Créée le <?php echo date('d/m/Y', strtotime($universite['date_creation'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activités récentes et informations système -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>
                            Activités récentes
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activites_recentes)): ?>
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-history fa-2x mb-2"></i>
                                <p>Aucune activité récente.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($activites_recentes as $activite): ?>
                                <div class="activity-item">
                                    <div class="d-flex align-items-center">
                                        <div class="activity-icon bg-info me-3">
                                            <i class="fas fa-info-circle"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($activite['message']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($activite['date_creation'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Informations système
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Version PHP:</strong><br>
                            <span class="text-muted"><?php echo PHP_VERSION; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Serveur:</strong><br>
                            <span class="text-muted"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu'; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Base de données:</strong><br>
                            <span class="text-muted">MySQL</span>
                        </div>
                        <div class="mb-3">
                            <strong>Dernière connexion:</strong><br>
                            <span class="text-muted"><?php echo date('d/m/Y H:i'); ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Statut système:</strong><br>
                            <span class="badge bg-success">Opérationnel</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animation des cartes au survol
        document.querySelectorAll('.stats-card, .management-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html> 