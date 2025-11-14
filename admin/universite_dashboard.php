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

$user_type = $_SESSION['user_type'];
$username = $_SESSION['username'] ?? '';
$user_id = $_SESSION['user_id'];

// Messages UI
$message = '';
$error = '';

// Traitement du formulaire d'identité visuelle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_branding') {
        try {
            $pdo = getDatabaseConnection();
            $universite_id = (int)$user_id;
            $slogan = sanitizeInput($_POST['slogan'] ?? '');

            // Met à jour le slogan
            $stmt = $pdo->prepare("UPDATE universites SET slogan = ? WHERE id = ?");
            $stmt->execute([$slogan, $universite_id]);

            // Upload du logo si fourni
            if (!empty($_FILES['logo']['name'])) {
                $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/svg+xml' => 'svg', 'image/webp' => 'webp'];
                $tmp = $_FILES['logo']['tmp_name'];
                $mime = @mime_content_type($tmp);
                if ($tmp && isset($allowed[$mime])) {
                    $ext = $allowed[$mime];
                    $baseDirFs = realpath(__DIR__ . '/../assets');
                    if ($baseDirFs === false) { $baseDirFs = __DIR__ . '/../assets'; }
                    $targetDirFs = $baseDirFs . '/uploads/universites/' . $universite_id;
                    if (!is_dir($targetDirFs)) { @mkdir($targetDirFs, 0777, true); }
                    foreach (['png','jpg','jpeg','svg','webp'] as $e) {
                        $f = $targetDirFs . '/logo.' . $e;
                        if (is_file($f)) { @unlink($f); }
                    }
                    $targetName = 'logo.' . $ext;
                    $targetPathFs = $targetDirFs . '/' . $targetName;
                    if (@move_uploaded_file($tmp, $targetPathFs)) {
                        $logoPathWeb = 'assets/uploads/universites/' . $universite_id . '/' . $targetName;
                        $upd = $pdo->prepare("UPDATE universites SET logo_path = ? WHERE id = ?");
                        $upd->execute([$logoPathWeb, $universite_id]);
                    } else {
                        $error = "Échec du téléchargement du logo.";
                    }
                } else {
                    $error = "Format de fichier non supporté pour le logo.";
                }
            }

            if (!$error) { $message = "Identité visuelle mise à jour avec succès."; }
        } catch (PDOException $ex) {
            $error = "Erreur lors de la mise à jour: " . $ex->getMessage();
        }
    }
}

// Fallback pour l'affichage du nom si username n'est pas défini
if (empty($username)) {
    if (!empty($_SESSION['user_data']['nom'])) {
        $username = $_SESSION['user_data']['nom'];
    } elseif (!empty($_SESSION['user_email'])) {
        $username = $_SESSION['user_email'];
    } else {
        $username = 'Université';
    }
}

// Récupération des données de l'université connectée
try {
    $pdo = getDatabaseConnection();
    
    // Utiliser l'ID de l'université connectée depuis la session
    $universite_id = (int)$user_id;
    
    // Informations de l'université
    $stmt = $pdo->prepare("SELECT * FROM universites WHERE id = ?");
    $stmt->execute([$universite_id]);
    $universite_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$universite_data) {
        // Si aucun enregistrement, afficher un message clair
        $universite_data = [
            'nom' => 'Université introuvable',
            'code' => '-',
            'adresse' => '—',
            'email' => '—',
            'telephone' => '—'
        ];
    }
    
    // Statistiques de l'université
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT f.id) as total_filieres
        FROM filieres f 
        INNER JOIN universite_filiere uf ON f.id = uf.filiere_id 
        WHERE uf.universite_id = ?
    ");
    $stmt->execute([$universite_id]);
    $total_filieres = $stmt->fetch(PDO::FETCH_ASSOC)['total_filieres'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT m.id) as total_matieres
        FROM matieres m 
        INNER JOIN filieres f ON m.filiere_id = f.id 
        INNER JOIN universite_filiere uf ON f.id = uf.filiere_id 
        WHERE uf.universite_id = ?
    ");
    $stmt->execute([$universite_id]);
    $total_matieres = $stmt->fetch(PDO::FETCH_ASSOC)['total_matieres'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT c.id) as total_classes
        FROM classes c 
        INNER JOIN filieres f ON c.filiere_id = f.id 
        INNER JOIN universite_filiere uf ON f.id = uf.filiere_id 
        WHERE uf.universite_id = ?
    ");
    $stmt->execute([$universite_id]);
    $total_classes = $stmt->fetch(PDO::FETCH_ASSOC)['total_classes'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT e.id) as total_etudiants
        FROM etudiants e 
        INNER JOIN filieres f ON e.filiere_id = f.id 
        INNER JOIN universite_filiere uf ON f.id = uf.filiere_id 
        WHERE uf.universite_id = ?
    ");
    $stmt->execute([$universite_id]);
    $total_etudiants = $stmt->fetch(PDO::FETCH_ASSOC)['total_etudiants'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT a.professeur_id) as total_professeurs
        FROM affectations a
        INNER JOIN classes c ON a.classe_id = c.id
        INNER JOIN universite_filiere uf ON c.filiere_id = uf.filiere_id
        WHERE uf.universite_id = ?
    ");
    $stmt->execute([$universite_id]);
    $total_professeurs = $stmt->fetch(PDO::FETCH_ASSOC)['total_professeurs'];
    
    // Activités récentes (utilise des champs horodatés existants)
    $stmt = $pdo->prepare("
        (
            SELECT 'matiere' AS type, m.nom AS nom, 'Matière créée' AS action, m.date_creation AS date
            FROM matieres m
            INNER JOIN filieres f ON m.filiere_id = f.id
            INNER JOIN universite_filiere uf ON uf.filiere_id = f.id AND uf.universite_id = ?
        )
        UNION ALL
        (
            SELECT 'etudiant' AS type, CONCAT(e.prenom, ' ', e.nom) AS nom, 'Étudiant inscrit' AS action, e.date_inscription AS date
            FROM etudiants e
            INNER JOIN filieres f ON e.filiere_id = f.id
            INNER JOIN universite_filiere uf ON uf.filiere_id = f.id AND uf.universite_id = ?
        )
        UNION ALL
        (
            SELECT 'affectation_classe' AS type,
                   CONCAT('Professeur #', pc.professeur_id, ' affecté à la classe #', pc.classe_id) AS nom,
                   'Affectation à une classe' AS action,
                   pc.date_affectation AS date
            FROM professeur_classe pc
            INNER JOIN classes c ON c.id = pc.classe_id
            INNER JOIN universite_filiere uf ON uf.filiere_id = c.filiere_id AND uf.universite_id = ?
        )
        UNION ALL
        (
            SELECT 'affectation_matiere' AS type,
                   CONCAT('Professeur #', mp.professeur_id, ' affecté à la matière #', mp.matiere_id) AS nom,
                   'Affectation à une matière' AS action,
                   mp.date_affectation AS date
            FROM matiere_professeur mp
            INNER JOIN matieres m ON m.id = mp.matiere_id
            INNER JOIN filieres f ON f.id = m.filiere_id
            INNER JOIN universite_filiere uf ON uf.filiere_id = f.id AND uf.universite_id = ?
        )
        ORDER BY date DESC
        LIMIT 5
    ");
    $stmt->execute([$universite_id, $universite_id, $universite_id, $universite_id]);
    $activites_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    $stmt->execute([$universite_id]);
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
    $stmt->execute([$universite_id]);
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
    $stmt->execute([$universite_id]);
    $stats_filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // En cas d'erreur, afficher des valeurs minimales et continuer
    $universite_data = $universite_data ?? [
        'nom' => 'Erreur chargement université',
        'code' => '-',
        'adresse' => '—',
        'email' => '—',
        'telephone' => '—'
    ];
    $total_filieres = $total_filieres ?? 0;
    $total_matieres = $total_matieres ?? 0;
    $total_classes = $total_classes ?? 0;
    $total_etudiants = $total_etudiants ?? 0;
    $total_professeurs = $total_professeurs ?? 0;
    $activites_recentes = $activites_recentes ?? [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Université - Portail des Résultats Universitaires</title>
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
            overflow-y: auto; /* ensure all items (incl. logout) are reachable */
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
        
        .welcome-section {
            background: var(--gradient-primary);
            color: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="p-4">
                <div class="text-center mb-4">
                    <i class="fas fa-university fa-3x text-primary mb-3"></i>
                    <h5 class="mb-0">Dashboard Université</h5>
                    <small class="text-muted">Administration</small>
                </div>
            
                
                <nav class="nav flex-column">
                    <a class="nav-link active" href="universite_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Tableau de bord
                    </a>
                    <a class="nav-link" href="filieres.php">
                        <i class="fas fa-graduation-cap"></i>
                        Gérer les filières
                    </a>
                    <a class="nav-link" href="matieres.php">
                        <i class="fas fa-book"></i>
                        Gérer les matières
                    </a>
                    <a class="nav-link" href="classes.php">
                        <i class="fas fa-users"></i>
                        Gérer les classes
                    </a>
                    <a class="nav-link" href="etudiants.php">
                        <i class="fas fa-user-graduate"></i>
                        Gérer les étudiants
                    </a>
                    <a class="nav-link" href="professeurs.php">
                        <i class="fas fa-chalkboard-teacher"></i>
                        Gérer les professeurs
                    </a>
                    <a class="nav-link" href="affectations.php">
                        <i class="fas fa-link"></i>
                        Gérer les affectations
                    </a>
                    <a class="nav-link" href="statistiques_resultats.php">
                        <i class="fas fa-chart-line"></i>
                        Statistiques des résultats
                    </a>
                    <hr>
                    <a class="nav-link" href="../logout.php">
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
                <div class="d-flex align-items-center">
                    <?php if (!empty($universite_data['logo_path'])): ?>
                        <img src="<?php echo '../' . htmlspecialchars($universite_data['logo_path']); ?>" alt="Logo Université" style="height:48px; width:auto; object-fit:contain; margin-right:12px;">
                    <?php else: ?>
                        <i class="fas fa-university fa-2x text-primary me-3"></i>
                    <?php endif; ?>
                    <div>
                        <h2 class="mb-1">Dashboard Université</h2>
                        <p class="text-muted mb-0">Bienvenue, <?php echo htmlspecialchars($username); ?></p>
                    </div>
                </div>
                <div class="text-end">
                    <div class="fw-bold"><?php echo htmlspecialchars($universite_data['nom']); ?></div>
                    <small class="text-muted d-block"><?php echo htmlspecialchars($universite_data['code']); ?></small>
                    <?php if (!empty($universite_data['slogan'])): ?>
                        <small class="text-muted fst-italic"><?php echo htmlspecialchars($universite_data['slogan']); ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3 class="mb-3">Bienvenue dans votre espace d'administration</h3>
                        <p class="mb-0 opacity-75">
                            Gérez vos filières, matières, classes, étudiants, professeurs et affectations 
                            depuis ce tableau de bord centralisé.
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <i class="fas fa-university fa-4x opacity-50"></i>
                    </div>
                </div>
            </div>
            
            <!-- University Profile -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-id-card me-2"></i>Profil de l'université</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="fw-semibold text-muted">Nom</div>
                            <div><?php echo htmlspecialchars($universite_data['nom'] ?? '—'); ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="fw-semibold text-muted">Code</div>
                            <div><?php echo htmlspecialchars($universite_data['code'] ?? '—'); ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="fw-semibold text-muted">Adresse</div>
                            <div><?php echo htmlspecialchars($universite_data['adresse'] ?? '—'); ?></div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="fw-semibold text-muted">Email</div>
                            <div><?php echo htmlspecialchars($universite_data['email'] ?? '—'); ?></div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="fw-semibold text-muted">Téléphone</div>
                            <div><?php echo htmlspecialchars($universite_data['telephone'] ?? '—'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Identité visuelle -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-image me-2"></i>Identité visuelle</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_branding">
                        <div class="row align-items-center g-3">
                            <div class="col-md-3 text-center">
                                <?php if (!empty($universite_data['logo_path'])): ?>
                                    <img src="<?php echo '../' . htmlspecialchars($universite_data['logo_path']); ?>" alt="Logo actuel" style="max-height:96px; max-width:100%; object-fit:contain;">
                                    <div class="text-muted mt-2"><small>Logo actuel</small></div>
                                <?php else: ?>
                                    <div class="text-muted"><i class="fas fa-image fa-3x"></i><br><small>Aucun logo</small></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Slogan</label>
                                <input type="text" class="form-control" name="slogan" value="<?php echo htmlspecialchars($universite_data['slogan'] ?? ''); ?>" placeholder="Ex: Scientia vincere tenebras">
                                <div class="form-text">Ce slogan apparaîtra sur les bulletins.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nouveau logo</label>
                                <input type="file" class="form-control" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp">
                                <div class="form-text">Formats: PNG, JPG, SVG, WEBP. Taille conseillée ≤ 300x300px.</div>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="stats-card text-center">
                        <div class="stats-icon bg-primary mb-3">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h4 class="mb-1"><?php echo $total_filieres; ?></h4>
                        <small class="text-muted">Filières</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card text-center">
                        <div class="stats-icon bg-success mb-3">
                            <i class="fas fa-book"></i>
                        </div>
                        <h4 class="mb-1"><?php echo $total_matieres; ?></h4>
                        <small class="text-muted">Matières</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card text-center">
                        <div class="stats-icon bg-info mb-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4 class="mb-1"><?php echo $total_classes; ?></h4>
                        <small class="text-muted">Classes</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card text-center">
                        <div class="stats-icon bg-warning mb-3">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h4 class="mb-1"><?php echo $total_etudiants; ?></h4>
                        <small class="text-muted">Étudiants</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card text-center">
                        <div class="stats-icon bg-danger mb-3">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <h4 class="mb-1"><?php echo $total_professeurs; ?></h4>
                        <small class="text-muted">Professeurs</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card text-center">
                        <div class="stats-icon bg-secondary mb-3">
                            <i class="fas fa-link"></i>
                        </div>
                        <h4 class="mb-1"><?php echo $total_filieres * $total_professeurs; ?></h4>
                        <small class="text-muted">Affectations</small>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activities -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-clock me-2"></i>
                                Activités récentes
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($activites_recentes as $activity): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($activity['action']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($activity['nom']); ?></small>
                                    </div>
                                    <small class="text-muted"><?php echo date('d/m/Y', strtotime($activity['date'])); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-cog me-2"></i>
                                Actions rapides
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="filieres.php" class="btn btn-outline-primary">
                                    <i class="fas fa-plus me-2"></i>
                                    Nouvelle filière
                                </a>
                                <a href="matieres.php" class="btn btn-outline-success">
                                    <i class="fas fa-plus me-2"></i>
                                    Nouvelle matière
                                </a>
                                <a href="classes.php" class="btn btn-outline-info">
                                    <i class="fas fa-plus me-2"></i>
                                    Nouvelle classe
                                </a>
                                <a href="etudiants.php" class="btn btn-outline-warning">
                                    <i class="fas fa-plus me-2"></i>
                                    Nouvel étudiant
                                </a>
                                <a href="professeurs.php" class="btn btn-outline-danger">
                                    <i class="fas fa-plus me-2"></i>
                                    Nouveau professeur
                                </a>
                                <a href="affectations.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-link me-2"></i>
                                    Nouvelle affectation
                                </a>
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
        
        navLinks.forEach(link => {
            if (link.getAttribute('href') === currentPage) {
                link.classList.add('active');
            }
        });
    </script>
</body>
</html> 