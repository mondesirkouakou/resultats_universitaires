<?php
require_once 'config.php';

// Vérifier que l'utilisateur est connecté et est un professeur
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'professeur') {
    redirect('student_login.php');
}

$pdo = getDatabaseConnection();
$professeur = [];

try {
    // Récupérer les informations du professeur + agrégation de ses matières
    $stmt = $pdo->prepare("
        SELECT p.*,
               mm.matieres_noms,
               mm.matiere_ids_csv
        FROM professeurs p
        LEFT JOIN (
            SELECT mp.professeur_id,
                   GROUP_CONCAT(m.nom ORDER BY m.nom SEPARATOR ', ') AS matieres_noms,
                   GROUP_CONCAT(m.id ORDER BY m.nom SEPARATOR ',') AS matiere_ids_csv
            FROM matiere_professeur mp
            JOIN matieres m ON m.id = mp.matiere_id
            GROUP BY mp.professeur_id
        ) mm ON mm.professeur_id = p.id
        WHERE p.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $professeur = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$professeur) {
        session_destroy();
        redirect('student_login.php');
    }
    // Récupérer le branding de l'université liée (via matières -> filière -> université)
    $ub = $pdo->prepare("\n        SELECT u.id as universite_id, u.nom as universite_nom, u.logo_path as universite_logo_path, u.slogan as universite_slogan\n        FROM professeurs p\n        LEFT JOIN matiere_professeur mp ON mp.professeur_id = p.id\n        LEFT JOIN matieres m ON m.id = mp.matiere_id\n        LEFT JOIN filieres f ON f.id = m.filiere_id\n        LEFT JOIN universite_filiere uf ON uf.filiere_id = f.id\n        LEFT JOIN universites u ON u.id = uf.universite_id\n        WHERE p.id = ?\n        ORDER BY u.id\n        LIMIT 1\n    ");
    $ub->execute([$_SESSION['user_id']]);
    $universite_branding = $ub->fetch(PDO::FETCH_ASSOC) ?: [];

    // Calcul des métriques réelles pour l'aperçu
    $profId = $_SESSION['user_id'];
    // 1) Classes assignées (via affectations et/ou professeur_classe)
    $qClasses = $pdo->prepare("\n        SELECT COUNT(DISTINCT t.classe_id) AS nb\n        FROM (\n            SELECT a.classe_id FROM affectations a WHERE a.professeur_id = ?\n            UNION\n            SELECT pc.classe_id FROM professeur_classe pc WHERE pc.professeur_id = ?\n        ) t\n    ");
    $qClasses->execute([$profId, $profId]);
    $classes_count = (int)($qClasses->fetchColumn() ?: 0);

    // 2) Étudiants total dans ces classes
    $students_count = 0;
    if ($classes_count > 0) {
        $qStudents = $pdo->prepare("\n            SELECT COUNT(*)\n            FROM etudiants e\n            WHERE e.classe_id IN (\n                SELECT t.classe_id FROM (\n                    SELECT a.classe_id FROM affectations a WHERE a.professeur_id = ?\n                    UNION\n                    SELECT pc.classe_id FROM professeur_classe pc WHERE pc.professeur_id = ?\n                ) t\n            )\n        ");
        $qStudents->execute([$profId, $profId]);
        $students_count = (int)($qStudents->fetchColumn() ?: 0);
    }

    // 3) Notes: attendu vs saisi vs en attente (sur les affectations du professeur)
    $notes_expected = 0;
    $notes_entered = 0;
    $pending_notes_count = 0;
    // Total attendu = somme des étudiants par affectation (classe, matière)
    $qExpected = $pdo->prepare("\n        SELECT COALESCE(SUM(cnt_students), 0) AS total_expected\n        FROM (\n            SELECT a.id, a.classe_id, a.matiere_id,\n                   (SELECT COUNT(*) FROM etudiants e WHERE e.classe_id = a.classe_id) AS cnt_students\n            FROM affectations a\n            WHERE a.professeur_id = ?\n        ) x\n    ");
    $qExpected->execute([$profId]);
    $notes_expected = (int)($qExpected->fetchColumn() ?: 0);

    // Total saisi = nombre de notes existantes pour ces paires (classe, matière)
    $qEntered = $pdo->prepare("\n        SELECT COALESCE(SUM(cnt_notes), 0) AS total_entered\n        FROM (\n            SELECT a.id, a.classe_id, a.matiere_id,\n                   (\n                       SELECT COUNT(*)\n                       FROM notes n\n                       JOIN etudiants e2 ON e2.id = n.etudiant_id\n                       WHERE n.matiere_id = a.matiere_id\n                         AND e2.classe_id = a.classe_id\n                   ) AS cnt_notes\n            FROM affectations a\n            WHERE a.professeur_id = ?\n        ) y\n    ");
    $qEntered->execute([$profId]);
    $notes_entered = (int)($qEntered->fetchColumn() ?: 0);
    $pending_notes_count = max($notes_expected - $notes_entered, 0);

    // Progression: si rien attendu, 0%; sinon ratio saisi/attendu
    $progress_percent = ($notes_expected > 0) ? min(100, max(0, round(($notes_entered / $notes_expected) * 100))) : 0;

    // Activités récentes
    // 1) Dernières notes saisies par ce professeur (via affectations)
    $recent_notes = [];
    $qRecentNotes = $pdo->prepare("\n        SELECT n.id as note_id, n.note, n.matiere_id, m.nom AS matiere_nom,\n               e.id AS etu_id, e.nom AS etu_nom, e.prenom AS etu_prenom, c.nom AS classe_nom\n        FROM notes n\n        JOIN matieres m ON m.id = n.matiere_id\n        JOIN etudiants e ON e.id = n.etudiant_id\n        JOIN classes c ON c.id = e.classe_id\n        JOIN affectations a ON a.matiere_id = n.matiere_id AND a.classe_id = c.id\n        WHERE a.professeur_id = ?\n        ORDER BY n.id DESC\n        LIMIT 5\n    ");
    $qRecentNotes->execute([$profId]);
    $recent_notes = $qRecentNotes->fetchAll(PDO::FETCH_ASSOC);

    // 2) Affectations récentes (matières) de ce professeur
    $recent_affectations_matieres = [];
    $qAffM = $pdo->prepare("\n        SELECT mp.date_affectation, m.nom AS matiere_nom\n        FROM matiere_professeur mp\n        JOIN matieres m ON m.id = mp.matiere_id\n        WHERE mp.professeur_id = ?\n        ORDER BY mp.date_affectation DESC\n        LIMIT 3\n    ");
    $qAffM->execute([$profId]);
    $recent_affectations_matieres = $qAffM->fetchAll(PDO::FETCH_ASSOC);

    // 3) Affectations récentes (classes) de ce professeur
    $recent_affectations_classes = [];
    $qAffC = $pdo->prepare("\n        SELECT pc.date_affectation, c.nom AS classe_nom\n        FROM professeur_classe pc\n        JOIN classes c ON c.id = pc.classe_id\n        WHERE pc.professeur_id = ?\n        ORDER BY pc.date_affectation DESC\n        LIMIT 3\n    ");
    $qAffC->execute([$profId]);
    $recent_affectations_classes = $qAffC->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des données';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Professeur - <?php echo htmlspecialchars($professeur['prenom'] . ' ' . $professeur['nom']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --professor-gradient: linear-gradient(135deg, #FF6B6B 0%, #ee5a52 100%);
            --success-gradient: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            --info-gradient: linear-gradient(135deg, #3498db 0%, #85c1e9 100%);
            --warning-gradient: linear-gradient(135deg, #f39c12 0%, #f7dc6f 100%);
            --purple-gradient: linear-gradient(135deg, #8e44ad 0%, #c39bd3 100%);
            --card-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            --hover-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        body {
            background: var(--primary-gradient);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(15px);
            box-shadow: var(--card-shadow);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: #FF6B6B !important;
        }

        .dashboard-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }

        .welcome-card {
            background: var(--professor-gradient);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(-20px, -20px) rotate(180deg); }
        }

        .card-header {
            border-radius: 20px 20px 0 0 !important;
            border: none;
            padding: 1.5rem;
            font-weight: 600;
        }

        .card-body {
            padding: 2rem;
        }

        .info-card {
            transition: all 0.3s ease;
            position: relative;
        }

        .info-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--professor-gradient);
            border-radius: 20px 20px 0 0;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .info-card:hover::after {
            opacity: 1;
        }

        .stat-icon {
            font-size: 3rem;
            opacity: 0.7;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 500;
        }

        .feature-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border-radius: 15px;
            position: relative;
            overflow: hidden;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.5s;
        }

        .feature-card:hover::before {
            left: 100%;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--professor-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 0.5rem;
        }

        .quick-stats {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1rem;
            margin: 1rem 0;
        }

        .progress-ring {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: conic-gradient(#FF6B6B 0deg 216deg, #e0e0e0 216deg 360deg);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .progress-ring::after {
            content: '60%';
            position: absolute;
            font-size: 0.8rem;
            font-weight: bold;
            color: #FF6B6B;
        }


        .glass-effect {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .text-gradient {
            background: var(--professor-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .grade-badge {
            background: var(--purple-gradient);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Fix dropdown z-index */
        .dropdown-menu {
            z-index: 9999 !important;
            position: absolute !important;
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2) !important;
        }

        .navbar {
            z-index: 1050 !important;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
            
            .card-body {
                padding: 1.5rem;
            }
            
            .stat-icon {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <?php if (!empty($universite_branding['universite_logo_path'])): ?>
                    <img src="<?php echo htmlspecialchars($universite_branding['universite_logo_path']); ?>" alt="Logo Université" style="height:36px; width:auto; object-fit:contain; margin-right:8px;">
                <?php else: ?>
                    <i class="fas fa-chalkboard-teacher me-2"></i>
                <?php endif; ?>
                <div class="d-flex flex-column">
                    <strong>Espace Professeur</strong>
                    <?php if (!empty($universite_branding['universite_slogan'])): ?>
                        <small class="text-muted" style="line-height:1;"><?php echo htmlspecialchars($universite_branding['universite_slogan']); ?></small>
                    <?php endif; ?>
                </div>
            </a>
            
            <div class="navbar-nav ms-auto d-flex align-items-center">
                
                
                
                <!-- User Menu -->
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                        <div class="user-avatar me-2">
                            <?php echo strtoupper(substr($professeur['prenom'], 0, 1) . substr($professeur['nom'], 0, 1)); ?>
                        </div>
                        <span class="d-none d-md-inline">
                            <?php echo htmlspecialchars($professeur['grade'] . ' ' . $professeur['prenom']); ?>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Mon Compte</h6></li>
                        <li><a class="dropdown-item" href="#profile">
                            <i class="fas fa-user me-2"></i>Profil
                        </a></li>
                        <li><a class="dropdown-item" href="change_password.php">
                            <i class="fas fa-key me-2"></i>Changer mot de passe
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header">Enseignement</h6></li>
                        <li><a class="dropdown-item" href="#classes">
                            <i class="fas fa-users me-2"></i>Mes Classes
                        </a></li>
                        <li><a class="dropdown-item" href="#grades">
                            <i class="fas fa-chart-bar me-2"></i>Saisie Notes
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Carte de bienvenue avec statistiques -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="dashboard-card welcome-card">
                    <div class="card-body p-4">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2 class="mb-3">
                                    <i class="fas fa-award me-2"></i>
                                    Bienvenue, <?php echo htmlspecialchars($professeur['grade'] . ' ' . $professeur['prenom']); ?> !
                                </h2>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="quick-stats glass-effect">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-book me-3 fs-4"></i>
                                                <div>
                                                    <small class="opacity-75">Matière(s) enseignée(s)</small>
                                                    <div class="fw-bold">
                                                        <?php if (!empty($professeur['matieres_noms'])): ?>
                                                            <?php echo htmlspecialchars($professeur['matieres_noms']); ?>
                                                        <?php else: ?>
                                                            <em>Non assignée</em>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="quick-stats glass-effect">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-clock me-3 fs-4"></i>
                                                <div>
                                                    <small class="opacity-75">Dernière connexion</small>
                                                    <div class="fw-bold"><?php echo date('d/m/Y à H:i'); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="d-flex align-items-center justify-content-end">
                                    <div class="progress-ring me-3"></div>
                                    <i class="fas fa-chalkboard-teacher stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informations personnelles -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="dashboard-card info-card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-user me-2"></i>Informations Personnelles
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <strong>Nom complet:</strong><br>
                                <?php echo htmlspecialchars($professeur['nom'] . ' ' . $professeur['prenom']); ?>
                            </div>
                            <div class="col-12">
                                <strong>Grade:</strong><br>
                                <span class="grade-badge fs-6">
                                    <?php echo htmlspecialchars($professeur['grade'] ?? 'Non spécifié'); ?>
                                </span>
                            </div>
                            <div class="col-12">
                                <strong>Email:</strong><br>
                                <a href="mailto:<?php echo htmlspecialchars($professeur['email']); ?>">
                                    <?php echo htmlspecialchars($professeur['email']); ?>
                                </a>
                            </div>
                            <?php if ($professeur['telephone']): ?>
                            <div class="col-12">
                                <strong>Téléphone:</strong><br>
                                <a href="tel:<?php echo htmlspecialchars($professeur['telephone']); ?>">
                                    <?php echo htmlspecialchars($professeur['telephone']); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="dashboard-card info-card h-100">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-book me-2"></i>Informations Académiques
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php if (!empty($professeur['matieres_noms'])): ?>
                            <div class="col-12">
                                <strong>Matière(s) enseignée(s):</strong><br>
                                <?php 
                                    $matieresList = array_filter(array_map('trim', explode(',', $professeur['matieres_noms'])));
                                ?>
                                <?php foreach ($matieresList as $mn): ?>
                                    <span class="badge bg-primary fs-6 me-1 mb-1"><?php echo htmlspecialchars($mn); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Aucune matière n'est actuellement assignée à votre compte.
                                    Contactez l'administration pour plus d'informations.
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <strong>Date de création du compte:</strong><br>
                                <?php echo $professeur['date_creation_compte'] ? date('d/m/Y', strtotime($professeur['date_creation_compte'])) : 'Non disponible'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="card-header" style="background: var(--info-gradient); color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-rocket me-2"></i>Outils d'Enseignement
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-3">
                                <div class="feature-card text-center p-4 border rounded-3 h-100">
                                    <div class="mb-3">
                                        <i class="fas fa-users fa-3x text-primary mb-3"></i>
                                    </div>
                                    <h5 class="text-gradient">Mes Classes</h5>
                                    <p class="text-muted mb-3">Gérer vos classes et étudiants</p>
                                    <a href="professor_classes.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-eye me-1"></i>Voir classes
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="feature-card text-center p-4 border rounded-3 h-100">
                                    <div class="mb-3">
                                        <i class="fas fa-chart-bar fa-3x text-success mb-3"></i>
                                    </div>
                                    <h5 class="text-gradient">Saisie Notes</h5>
                                    <p class="text-muted mb-3">Évaluer et noter vos étudiants</p>
                                    <a href="professor_grades.php" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-edit me-1"></i>Saisir notes
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="feature-card text-center p-4 border rounded-3 h-100">
                                    <div class="mb-3">
                                        <i class="fas fa-file-alt fa-3x text-info mb-3"></i>
                                    </div>
                                    <h5 class="text-gradient">Rapports</h5>
                                    <p class="text-muted mb-3">Statistiques et analyses</p>
                                    <a href="professor_reports.php" class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-chart-line me-1"></i>Voir rapports
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques et activités récentes -->
        <div class="row">
            <div class="col-md-8">
                <div class="dashboard-card info-card h-100">
                    <div class="card-header" style="background: var(--warning-gradient); color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie me-2"></i>Aperçu de l'Enseignement
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="display-4 text-primary fw-bold"><?php echo (int)$classes_count; ?></div>
                                    <small class="text-muted">Classes assignées</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="display-4 text-success fw-bold"><?php echo (int)$students_count; ?></div>
                                    <small class="text-muted">Étudiants total</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="display-4 text-warning fw-bold"><?php echo (int)$pending_notes_count; ?></div>
                                    <small class="text-muted">Notes à saisir</small>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar" style="width: <?php echo $progress_percent; ?>%; background: var(--professor-gradient);" role="progressbar" aria-valuenow="<?php echo $progress_percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <small class="text-muted mt-2 d-block">Progression des saisies de notes: <?php echo $progress_percent; ?>%</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-card info-card h-100">
                    <div class="card-header" style="background: var(--purple-gradient); color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-tasks me-2"></i>Activités Récentes
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Dernières notes saisies -->
                        <h6 class="mb-3"><i class="fas fa-plus-circle text-success me-2"></i>Dernières notes saisies</h6>
                        <?php if (!empty($recent_notes)): ?>
                            <?php foreach ($recent_notes as $rn): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-check-circle text-success"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-2">
                                        <small>
                                            <?php echo htmlspecialchars($rn['matiere_nom']); ?> · <?php echo htmlspecialchars($rn['classe_nom']); ?> · 
                                            <?php echo htmlspecialchars($rn['etu_prenom'] . ' ' . $rn['etu_nom']); ?> → 
                                            <strong><?php echo htmlspecialchars($rn['note']); ?></strong>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-end mb-3">
                                <a href="professor_grades.php" class="btn btn-outline-success btn-sm"><i class="fas fa-edit me-1"></i>Gérer les notes</a>
                            </div>
                        <?php else: ?>
                            <div class="text-muted mb-3"><small>Aucune note récente.</small></div>
                        <?php endif; ?>

                        <!-- Affectations récentes (matières) -->
                        <h6 class="mb-2"><i class="fas fa-book text-primary me-2"></i>Affectations matières</h6>
                        <?php if (!empty($recent_affectations_matieres)): ?>
                            <?php foreach ($recent_affectations_matieres as $am): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-book-open text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-2">
                                        <small>
                                            <?php echo htmlspecialchars($am['matiere_nom']); ?> · 
                                            <?php echo $am['date_affectation'] ? date('d/m/Y', strtotime($am['date_affectation'])) : ''; ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-muted mb-2"><small>Aucune nouvelle affectation de matière.</small></div>
                        <?php endif; ?>

                        <!-- Affectations récentes (classes) -->
                        <h6 class="mb-2"><i class="fas fa-users text-info me-2"></i>Affectations classes</h6>
                        <?php if (!empty($recent_affectations_classes)): ?>
                            <?php foreach ($recent_affectations_classes as $ac): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-user-check text-info"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-2">
                                        <small>
                                            <?php echo htmlspecialchars($ac['classe_nom']); ?> · 
                                            <?php echo $ac['date_affectation'] ? date('d/m/Y', strtotime($ac['date_affectation'])) : ''; ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-end">
                                <a href="professor_classes.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-eye me-1"></i>Voir mes classes</a>
                            </div>
                        <?php else: ?>
                            <div class="text-muted"><small>Aucune nouvelle affectation de classe.</small></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
