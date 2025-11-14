<?php
require_once 'config.php';

// Vérification de la session
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_type = $_SESSION['user_type'];
$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// Données simulées pour la démo
$user_data = [
    'etudiant' => [
        'nom' => 'Jean Dupont',
        'matricule' => 'ETU2024001',
        'filiere' => 'Informatique',
        'niveau' => 'L2',
        'moyenne' => 14.5,
        'credits' => 120
    ],
    'professeur' => [
        'nom' => 'Dr. Marie Martin',
        'code' => 'PROF2024001',
        'departement' => 'Informatique',
        'matieres' => ['Programmation Web', 'Base de données', 'Algorithmes']
    ],
    'admin_principal' => [
        'nom' => 'Admin Principal',
        'code' => 'ADMIN001',
        'role' => 'Administrateur Principal'
    ],
    'universite' => [
        'nom' => 'Université de Paris',
        'code' => 'UNIV001',
        'role' => 'Administrateur Université'
    ],
    'parent' => [
        'nom' => 'Pierre Dupont',
        'code' => 'PAR2024001',
        'enfant' => 'Jean Dupont'
    ]
];

$current_user = $user_data[$user_type] ?? [];

// Données simulées pour les résultats
$resultats = [
    'Programmation Web' => [
        'note' => 16,
        'coefficient' => 3,
        'evaluations' => [
            ['type' => 'TP', 'note' => 18, 'coefficient' => 1],
            ['type' => 'Examen', 'note' => 14, 'coefficient' => 2]
        ]
    ],
    'Base de données' => [
        'note' => 15,
        'coefficient' => 4,
        'evaluations' => [
            ['type' => 'TP', 'note' => 16, 'coefficient' => 1],
            ['type' => 'Examen', 'note' => 14, 'coefficient' => 3]
        ]
    ],
    'Algorithmes' => [
        'note' => 17,
        'coefficient' => 3,
        'evaluations' => [
            ['type' => 'TP', 'note' => 19, 'coefficient' => 1],
            ['type' => 'Examen', 'note' => 16, 'coefficient' => 2]
        ]
    ]
];

$periodes = [
    ['id' => 1, 'nom' => 'Semestre 1', 'debut' => '2024-09-01', 'fin' => '2024-12-31', 'statut' => 'Terminé'],
    ['id' => 2, 'nom' => 'Semestre 2', 'debut' => '2025-01-01', 'fin' => '2025-05-31', 'statut' => 'En cours']
];

// Récupération des filières depuis la base de données
$filieres = [];
try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->query("
        SELECT f.*, GROUP_CONCAT(u.nom SEPARATOR ', ') as universites_noms
        FROM filieres f 
        LEFT JOIN universite_filiere uf ON f.id = uf.filiere_id
        LEFT JOIN universites u ON uf.universite_id = u.id 
        WHERE f.statut = 'actif'
        GROUP BY f.id, f.nom, f.description, f.duree_etudes, f.niveau_entree, f.date_creation, f.statut
        ORDER BY f.nom
    ");
    $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // En cas d'erreur, on continue avec un tableau vide
    $filieres = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Portail des Résultats Universitaires</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
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
            top: 0;
            left: 0;
            width: 280px;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .sidebar-header {
            background: var(--gradient-primary);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .sidebar-nav {
            padding: 20px 0;
        }
        
        .nav-item {
            margin-bottom: 5px;
        }
        
        .nav-link {
            color: #6c757d;
            padding: 12px 25px;
            border-radius: 0 25px 25px 0;
            margin-right: 20px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .nav-link:hover, .nav-link.active {
            background: var(--gradient-primary);
            color: white;
            transform: translateX(5px);
        }
        
        .main-content {
            margin-left: 280px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .top-bar {
            background: white;
            padding: 15px 30px;
            border-radius: 15px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
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
        
        .result-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .result-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }
        
        .grade-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .grade-excellent { background: #d4edda; color: #155724; }
        .grade-good { background: #d1ecf1; color: #0c5460; }
        .grade-average { background: #fff3cd; color: #856404; }
        .grade-poor { background: #f8d7da; color: #721c24; }
        
        .progress-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: conic-gradient(var(--primary-color) 0deg 180deg, #e9ecef 180deg 360deg);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        .progress-circle::before {
            content: '';
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            position: absolute;
        }
        
        .progress-text {
            position: relative;
            z-index: 1;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar-toggle {
                display: block;
            }
        }
        
        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-graduation-cap fa-2x mb-3"></i>
                <h5 class="mb-0">Portail Universitaire</h5>
                <small class="opacity-75"><?php echo ucfirst($user_type); ?></small>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#dashboard">
                            <i class="fas fa-tachometer-alt"></i>
                            Tableau de bord
                        </a>
                    </li>
                    
                    <?php if ($user_type === 'etudiant'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="#resultats">
                                <i class="fas fa-chart-line"></i>
                                Mes résultats
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#evaluations">
                                <i class="fas fa-clipboard-list"></i>
                                Évaluations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#progression">
                                <i class="fas fa-chart-bar"></i>
                                Progression
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($user_type === 'professeur'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="#cours">
                                <i class="fas fa-chalkboard-teacher"></i>
                                Mes cours
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#evaluations">
                                <i class="fas fa-edit"></i>
                                Saisir notes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#statistiques">
                                <i class="fas fa-chart-pie"></i>
                                Statistiques
                            </a>
                        </li>
                    <?php endif; ?>
                    
                                         <?php if ($user_type === 'admin_principal'): ?>
                         <li class="nav-item">
                             <a class="nav-link" href="#universites">
                                 <i class="fas fa-university"></i>
                                 Gestion universités
                             </a>
                         </li>
                         <li class="nav-item">
                             <a class="nav-link" href="#systeme">
                                 <i class="fas fa-cogs"></i>
                                 Système
                             </a>
                         </li>
                     <?php endif; ?>
                     
                     <?php if ($user_type === 'universite'): ?>
                         <li class="nav-item">
                             <a class="nav-link" href="#filieres">
                                 <i class="fas fa-sitemap"></i>
                                 Filières
                             </a>
                         </li>
                         <li class="nav-item">
                             <a class="nav-link" href="#matieres">
                                 <i class="fas fa-book"></i>
                                 Matières
                             </a>
                         </li>
                         <li class="nav-item">
                             <a class="nav-link" href="#classes">
                                 <i class="fas fa-users"></i>
                                 Classes
                             </a>
                         </li>
                     <?php endif; ?>
                    
                    <?php if ($user_type === 'parent'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="#enfant">
                                <i class="fas fa-child"></i>
                                Mon enfant
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#suivi">
                                <i class="fas fa-eye"></i>
                                Suivi
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="nav-item mt-4">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            Déconnexion
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($current_user['nom'] ?? $username, 0, 1)); ?>
                    </div>
                    <div>
                        <h6 class="mb-0"><?php echo $current_user['nom'] ?? $username; ?></h6>
                        <small class="text-muted"><?php echo ucfirst($user_type); ?></small>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#profileModal">
                        <i class="fas fa-user-cog"></i>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </div>
            
            <!-- Dashboard Content -->
            <div id="dashboard-content">
                <?php if ($user_type === 'etudiant'): ?>
                    <!-- Étudiant Dashboard -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card text-center">
                                <div class="stats-icon bg-primary">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <h4><?php echo $current_user['moyenne']; ?>/20</h4>
                                <p class="text-muted mb-0">Moyenne générale</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card text-center">
                                <div class="stats-icon bg-success">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <h4><?php echo $current_user['credits']; ?></h4>
                                <p class="text-muted mb-0">Crédits obtenus</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card text-center">
                                <div class="stats-icon bg-warning">
                                    <i class="fas fa-book"></i>
                                </div>
                                <h4><?php echo count($resultats); ?></h4>
                                <p class="text-muted mb-0">Matières suivies</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card text-center">
                                <div class="progress-circle">
                                    <div class="progress-text">75%</div>
                                </div>
                                <p class="text-muted mt-2 mb-0">Progression</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-line me-2"></i>
                                        Mes résultats par matière
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($resultats as $matiere => $resultat): ?>
                                        <div class="result-card">
                                            <div class="row align-items-center">
                                                <div class="col-md-6">
                                                    <h6 class="mb-1"><?php echo $matiere; ?></h6>
                                                    <small class="text-muted">Coefficient: <?php echo $resultat['coefficient']; ?></small>
                                                </div>
                                                <div class="col-md-3 text-center">
                                                    <span class="grade-badge grade-<?php echo $resultat['note'] >= 16 ? 'excellent' : ($resultat['note'] >= 14 ? 'good' : ($resultat['note'] >= 12 ? 'average' : 'poor')); ?>">
                                                        <?php echo $resultat['note']; ?>/20
                                                    </span>
                                                </div>
                                                <div class="col-md-3 text-end">
                                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detailModal" data-matiere="<?php echo $matiere; ?>">
                                                        <i class="fas fa-eye"></i> Détails
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-calendar-alt me-2"></i>
                                        Périodes académiques
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($periodes as $periode): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <h6 class="mb-0"><?php echo $periode['nom']; ?></h6>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y', strtotime($periode['debut'])); ?> - 
                                                    <?php echo date('d/m/Y', strtotime($periode['fin'])); ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-<?php echo $periode['statut'] === 'En cours' ? 'success' : 'secondary'; ?>">
                                                <?php echo $periode['statut']; ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($user_type === 'professeur'): ?>
                    <!-- Professeur Dashboard -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="stats-card text-center">
                                <div class="stats-icon bg-primary">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                </div>
                                <h4><?php echo count($current_user['matieres']); ?></h4>
                                <p class="text-muted mb-0">Matières enseignées</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card text-center">
                                <div class="stats-icon bg-success">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h4>150</h4>
                                <p class="text-muted mb-0">Étudiants</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card text-center">
                                <div class="stats-icon bg-warning">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <h4>85%</h4>
                                <p class="text-muted mb-0">Notes saisies</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-book me-2"></i>
                                        Mes matières
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($current_user['matieres'] as $matiere): ?>
                                        <div class="result-card">
                                            <div class="row align-items-center">
                                                <div class="col-md-6">
                                                    <h6 class="mb-1"><?php echo $matiere; ?></h6>
                                                    <small class="text-muted">L2 Informatique</small>
                                                </div>
                                                <div class="col-md-3 text-center">
                                                    <span class="badge bg-info">45 étudiants</span>
                                                </div>
                                                <div class="col-md-3 text-end">
                                                    <button class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i> Saisir notes
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-pie me-2"></i>
                                        Statistiques
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Moyenne générale</span>
                                            <span>14.2/20</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar" style="width: 71%"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Taux de réussite</span>
                                            <span>78%</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" style="width: 78%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                                 <?php elseif ($user_type === 'admin_principal'): ?>
                     <!-- Admin Principal Dashboard -->
                     <div class="row mb-4">
                         <div class="col-md-3">
                             <div class="stats-card text-center">
                                 <div class="stats-icon bg-primary">
                                     <i class="fas fa-university"></i>
                                 </div>
                                 <h4>8</h4>
                                 <p class="text-muted mb-0">Universités</p>
                             </div>
                         </div>
                         <div class="col-md-3">
                             <div class="stats-card text-center">
                                 <div class="stats-icon bg-success">
                                     <i class="fas fa-users"></i>
                                 </div>
                                 <h4>5000+</h4>
                                 <p class="text-muted mb-0">Étudiants</p>
                             </div>
                         </div>
                         <div class="col-md-3">
                             <div class="stats-card text-center">
                                 <div class="stats-icon bg-warning">
                                     <i class="fas fa-chalkboard-teacher"></i>
                                 </div>
                                 <h4>200+</h4>
                                 <p class="text-muted mb-0">Professeurs</p>
                             </div>
                         </div>
                         <div class="col-md-3">
                             <div class="stats-card text-center">
                                 <div class="stats-icon bg-info">
                                     <i class="fas fa-sitemap"></i>
                                 </div>
                                 <h4>25</h4>
                                 <p class="text-muted mb-0">Filières</p>
                             </div>
                         </div>
                     </div>
                     
                     <div class="row">
                         <div class="col-lg-8">
                             <div class="card">
                                 <div class="card-header bg-primary text-white">
                                     <h5 class="mb-0">
                                         <i class="fas fa-cogs me-2"></i>
                                         Administration Principale
                                     </h5>
                                 </div>
                                 <div class="card-body">
                                     <div class="row g-3">
                                         <div class="col-md-6">
                                             <div class="result-card text-center">
                                                 <i class="fas fa-university fa-2x text-primary mb-3"></i>
                                                 <h6>Gérer les universités</h6>
                                                 <a href="admin_principal/dashboard.php" class="btn btn-primary btn-sm">Dashboard Admin Principal</a>
                                             </div>
                                         </div>
                                         <div class="col-md-6">
                                             <div class="result-card text-center">
                                                 <i class="fas fa-chart-line fa-2x text-success mb-3"></i>
                                                 <h6>Statistiques globales</h6>
                                                 <a href="admin_principal/statistiques.php" class="btn btn-success btn-sm">Voir les Statistiques</a>
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
                                         <i class="fas fa-chart-line me-2"></i>
                                         Statistiques système
                                     </h5>
                                 </div>
                                 <div class="card-body">
                                     <div class="mb-3">
                                         <div class="d-flex justify-content-between mb-1">
                                             <span>Utilisation serveur</span>
                                             <span>65%</span>
                                         </div>
                                         <div class="progress">
                                             <div class="progress-bar" style="width: 65%"></div>
                                         </div>
                                     </div>
                                     <div class="mb-3">
                                         <div class="d-flex justify-content-between mb-1">
                                             <span>Connexions actives</span>
                                             <span>342</span>
                                         </div>
                                         <div class="progress">
                                             <div class="progress-bar bg-success" style="width: 85%"></div>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                         </div>
                     </div>
                     
                 <?php elseif ($user_type === 'universite'): ?>
                     <!-- Université Dashboard -->
                                         <div class="row mb-4">
                         <div class="col-md-3">
                             <div class="stats-card text-center">
                                 <div class="stats-icon bg-primary">
                                     <i class="fas fa-users"></i>
                                 </div>
                                 <h4>1200+</h4>
                                 <p class="text-muted mb-0">Étudiants</p>
                             </div>
                         </div>
                         <div class="col-md-3">
                             <div class="stats-card text-center">
                                 <div class="stats-icon bg-success">
                                     <i class="fas fa-chalkboard-teacher"></i>
                                 </div>
                                 <h4>85+</h4>
                                 <p class="text-muted mb-0">Professeurs</p>
                             </div>
                         </div>
                         <div class="col-md-3">
                             <div class="stats-card text-center">
                                 <div class="stats-icon bg-warning">
                                     <i class="fas fa-sitemap"></i>
                                 </div>
                                 <h4>12</h4>
                                 <p class="text-muted mb-0">Filières</p>
                             </div>
                         </div>
                         <div class="col-md-3">
                             <div class="stats-card text-center">
                                 <div class="stats-icon bg-info">
                                     <i class="fas fa-university"></i>
                                 </div>
                                 <h4>4</h4>
                                 <p class="text-muted mb-0">UFR</p>
                             </div>
                         </div>
                     </div>
                     
                     <div class="row">
                         <div class="col-lg-8">
                             <div class="card">
                                 <div class="card-header bg-primary text-white">
                                     <h5 class="mb-0">
                                         <i class="fas fa-cogs me-2"></i>
                                         Gestion Universitaire
                                     </h5>
                                 </div>
                                 <div class="card-body">
                                     <div class="row g-3">
                                         <div class="col-md-6">
                                             <div class="result-card text-center">
                                                 <i class="fas fa-sitemap fa-2x text-primary mb-3"></i>
                                                 <h6>Gérer les filières</h6>
                                                 <a href="admin/filieres.php" class="btn btn-primary btn-sm">Gérer les Filières</a>
                                             </div>
                                         </div>
                                         <div class="col-md-6">
                                             <div class="result-card text-center">
                                                 <i class="fas fa-book fa-2x text-success mb-3"></i>
                                                 <h6>Gérer les matières</h6>
                                                 <a href="admin/matieres.php" class="btn btn-success btn-sm">Gérer les Matières</a>
                                             </div>
                                         </div>
                                         <div class="col-md-6">
                                             <div class="result-card text-center">
                                                 <i class="fas fa-users fa-2x text-warning mb-3"></i>
                                                 <h6>Gérer les classes</h6>
                                                 <a href="admin/classes.php" class="btn btn-warning btn-sm">Gérer les Classes</a>
                                             </div>
                                         </div>
                                         <div class="col-md-6">
                                             <div class="result-card text-center">
                                                 <i class="fas fa-user-graduate fa-2x text-info mb-3"></i>
                                                 <h6>Gérer les étudiants</h6>
                                                 <a href="admin/etudiants.php" class="btn btn-info btn-sm">Gérer les Étudiants</a>
                                             </div>
                                         </div>
                                         <div class="col-md-6">
                                             <div class="result-card text-center">
                                                 <i class="fas fa-chalkboard-teacher fa-2x text-secondary mb-3"></i>
                                                 <h6>Gérer les professeurs</h6>
                                                 <a href="admin/professeurs.php" class="btn btn-secondary btn-sm">Gérer les Professeurs</a>
                                             </div>
                                         </div>
                                         <div class="col-md-6">
                                             <div class="result-card text-center">
                                                 <i class="fas fa-link fa-2x text-danger mb-3"></i>
                                                 <h6>Gérer les affectations</h6>
                                                 <a href="admin/affectations.php" class="btn btn-danger btn-sm">Gérer les Affectations</a>
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
                                         <i class="fas fa-chart-line me-2"></i>
                                         Statistiques université
                                     </h5>
                                 </div>
                                 <div class="card-body">
                                     <div class="mb-3">
                                         <div class="d-flex justify-content-between mb-1">
                                             <span>Taux de réussite</span>
                                             <span>78%</span>
                                         </div>
                                         <div class="progress">
                                             <div class="progress-bar bg-success" style="width: 78%"></div>
                                         </div>
                                     </div>
                                     <div class="mb-3">
                                         <div class="d-flex justify-content-between mb-1">
                                             <span>Moyenne générale</span>
                                             <span>14.2/20</span>
                                         </div>
                                         <div class="progress">
                                             <div class="progress-bar" style="width: 71%"></div>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                         </div>
                     </div>
                    
                <?php endif; ?>
                
                <!-- Section Filières - Visible pour tous les utilisateurs -->
                <div id="filieres" class="content-section" style="display: none;">
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-sitemap me-2"></i>
                                        Filières disponibles
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($filieres)): ?>
                                        <div class="alert alert-info text-center">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Aucune filière disponible pour le moment.
                                        </div>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php foreach ($filieres as $filiere): ?>
                                                <div class="col-md-6 col-lg-4 mb-4">
                                                    <div class="card h-100 shadow-sm">
                                                        <div class="card-body">
                                                            <h6 class="card-title text-primary">
                                                                <i class="fas fa-graduation-cap me-2"></i>
                                                                <?php echo htmlspecialchars($filiere['nom']); ?>
                                                            </h6>
                                                            
                                                            <?php if (!empty($filiere['description'])): ?>
                                                                <p class="card-text text-muted small">
                                                                    <?php echo htmlspecialchars($filiere['description']); ?>
                                                                </p>
                                                            <?php endif; ?>
                                                            
                                                            <div class="row text-center mt-3">
                                                                <div class="col-6">
                                                                    <div class="border-end">
                                                                        <h6 class="text-success mb-0"><?php echo $filiere['duree_etudes']; ?></h6>
                                                                        <small class="text-muted">Années</small>
                                                                    </div>
                                                                </div>
                                                                <div class="col-6">
                                                                    <h6 class="text-info mb-0"><?php echo htmlspecialchars($filiere['niveau_entree']); ?></h6>
                                                                    <small class="text-muted">Niveau requis</small>
                                                                </div>
                                                            </div>
                                                            
                                                            <?php if (!empty($filiere['universites_noms'])): ?>
                                                                <div class="mt-3">
                                                                    <small class="text-muted">
                                                                        <i class="fas fa-university me-1"></i>
                                                                        <?php echo htmlspecialchars($filiere['universites_noms']); ?>
                                                                    </small>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="card-footer bg-light">
                                                            <small class="text-muted">
                                                                <i class="fas fa-calendar me-1"></i>
                                                                Créée le <?php echo date('d/m/Y', strtotime($filiere['date_creation'])); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($user_type === 'parent'): ?>
                    <!-- Parent Dashboard -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="stats-card text-center">
                                <div class="stats-icon bg-primary">
                                    <i class="fas fa-child"></i>
                                </div>
                                <h4><?php echo $current_user['enfant']; ?></h4>
                                <p class="text-muted mb-0">Mon enfant</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card text-center">
                                <div class="stats-icon bg-success">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <h4>14.5/20</h4>
                                <p class="text-muted mb-0">Moyenne générale</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card text-center">
                                <div class="stats-icon bg-warning">
                                    <i class="fas fa-eye"></i>
                                </div>
                                <h4>12</h4>
                                <p class="text-muted mb-0">Consultations ce mois</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-line me-2"></i>
                                        Résultats de mon enfant
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($resultats as $matiere => $resultat): ?>
                                        <div class="result-card">
                                            <div class="row align-items-center">
                                                <div class="col-md-6">
                                                    <h6 class="mb-1"><?php echo $matiere; ?></h6>
                                                    <small class="text-muted">Coefficient: <?php echo $resultat['coefficient']; ?></small>
                                                </div>
                                                <div class="col-md-3 text-center">
                                                    <span class="grade-badge grade-<?php echo $resultat['note'] >= 16 ? 'excellent' : ($resultat['note'] >= 14 ? 'good' : ($resultat['note'] >= 12 ? 'average' : 'poor')); ?>">
                                                        <?php echo $resultat['note']; ?>/20
                                                    </span>
                                                </div>
                                                <div class="col-md-3 text-end">
                                                    <button class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i> Détails
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-calendar-alt me-2"></i>
                                        Activité récente
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary rounded-circle p-2 me-3">
                                                <i class="fas fa-eye text-white"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0">Consultation résultats</h6>
                                                <small class="text-muted">Il y a 2 heures</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-success rounded-circle p-2 me-3">
                                                <i class="fas fa-download text-white"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0">Téléchargement bulletin</h6>
                                                <small class="text-muted">Hier</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Détails des évaluations</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="detailContent">
                        <!-- Contenu dynamique -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="profileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Profil utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="user-avatar mx-auto mb-3">
                            <?php echo strtoupper(substr($current_user['nom'] ?? $username, 0, 1)); ?>
                        </div>
                        <h5><?php echo $current_user['nom'] ?? $username; ?></h5>
                        <p class="text-muted"><?php echo ucfirst($user_type); ?></p>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <strong>Nom d'utilisateur:</strong><br>
                            <span class="text-muted"><?php echo $username; ?></span>
                        </div>
                        <div class="col-6">
                            <strong>ID:</strong><br>
                            <span class="text-muted"><?php echo $user_id; ?></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <a href="logout.php" class="btn btn-danger">Déconnexion</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show');
        });
        
        // Navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('href').startsWith('#')) {
                    e.preventDefault();
                    
                    // Remove active class from all links
                    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                    
                    // Add active class to clicked link
                    this.classList.add('active');
                    
                    // Handle navigation (you can add AJAX calls here)
                    const section = this.getAttribute('href').substring(1);
                    console.log('Navigating to:', section);
                }
            });
        });
        
        // Detail modal
        document.getElementById('detailModal').addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const matiere = button.getAttribute('data-matiere');
            const resultat = <?php echo json_encode($resultats); ?>[matiere];
            
            if (resultat) {
                const content = document.getElementById('detailContent');
                content.innerHTML = `
                    <h6>${matiere}</h6>
                    <p class="text-muted">Note finale: <strong>${resultat.note}/20</strong></p>
                    <hr>
                    <h6>Détail des évaluations:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Note</th>
                                    <th>Coefficient</th>
                                    <th>Note pondérée</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${resultat.evaluations.map(eval => `
                                    <tr>
                                        <td>${eval.type}</td>
                                        <td>${eval.note}/20</td>
                                        <td>${eval.coefficient}</td>
                                        <td>${(eval.note * eval.coefficient).toFixed(2)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }
        });
        
        // Gestion de la navigation vers les filières
        document.addEventListener('DOMContentLoaded', function() {
            const filieresLink = document.querySelector('a[href="#filieres"]');
            const filieresSection = document.getElementById('filieres');
            const dashboardContent = document.getElementById('dashboard-content');
            
            if (filieresLink && filieresSection) {
                filieresLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Masquer le contenu du dashboard
                    dashboardContent.style.display = 'none';
                    
                    // Afficher la section filières
                    filieresSection.style.display = 'block';
                    
                    // Mettre à jour les liens actifs
                    document.querySelectorAll('.nav-link').forEach(link => {
                        link.classList.remove('active');
                    });
                    filieresLink.classList.add('active');
                });
            }
            
            // Gestion du retour au dashboard
            document.querySelectorAll('.nav-link:not([href="#filieres"])').
                forEach(link => {
                    link.addEventListener('click', function() {
                        if (filieresSection) {
                            filieresSection.style.display = 'none';
                        }
                        if (dashboardContent) {
                            dashboardContent.style.display = 'block';
                        }
                    });
                });
        });
    </script>
</body>
</html> 