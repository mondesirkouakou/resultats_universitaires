<?php
require_once 'config.php';

// Vérifier que l'utilisateur est connecté et est un étudiant
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'etudiant') {
    redirect('student_login.php');
}

$pdo = getDatabaseConnection();
$etudiant = [];
$matieres = [];
$notes = [];
$bulletin = [];

try {
    // Récupérer les informations de l'étudiant
    $stmt = $pdo->prepare("
        SELECT e.*, c.nom as classe_nom, c.annee, c.semestre, f.nom as filiere_nom,
               u.nom as universite_nom, u.logo_path as universite_logo_path, u.slogan as universite_slogan
        FROM etudiants e 
        LEFT JOIN classes c ON e.classe_id = c.id 
        LEFT JOIN filieres f ON e.filiere_id = f.id 
        LEFT JOIN universite_filiere uf ON f.id = uf.filiere_id
        LEFT JOIN universites u ON uf.universite_id = u.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$etudiant) {
        session_destroy();
        redirect('student_login.php');
    }

    // Récupérer les matières de l'étudiant avec les professeurs
    $stmt = $pdo->prepare("
        SELECT DISTINCT m.id, m.nom as matiere_nom, m.code as matiere_code, 
               p.nom as prof_nom, p.prenom as prof_prenom, p.grade as prof_grade
        FROM matieres m
        INNER JOIN affectations a ON m.id = a.matiere_id
        INNER JOIN professeurs p ON a.professeur_id = p.id
        WHERE a.classe_id = ?
        ORDER BY m.nom
    ");
    $stmt->execute([$etudiant['classe_id']]);
    $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Détecter la présence de la colonne type_note (nouveau schéma) et récupérer les notes
    $hasTypeNote = false;
    $chkCol = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notes' AND COLUMN_NAME = 'type_note'");
    $chkCol->execute();
    $hasTypeNote = ((int)$chkCol->fetchColumn() > 0);

    if ($hasTypeNote) {
        $stmt = $pdo->prepare("
            SELECT n.*, m.nom as matiere_nom, m.code as matiere_code,
                   n.note, n.type_note, n.annee_academique
            FROM notes n
            INNER JOIN matieres m ON n.matiere_id = m.id
            WHERE n.etudiant_id = ?
            ORDER BY m.nom, n.type_note
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT n.*, m.nom as matiere_nom, m.code as matiere_code,
                   n.note, n.session, n.annee_academique
            FROM notes n
            INNER JOIN matieres m ON n.matiere_id = m.id
            WHERE n.etudiant_id = ?
            ORDER BY m.nom, n.session
        ");
    }
    $stmt->execute([$_SESSION['user_id']]);
    $notes_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organiser les notes par matière
    foreach ($notes_raw as $note) {
        $notes[$note['matiere_id']][] = $note;
    }

    // Calculer le bulletin (moyennes par matière)
    foreach ($matieres as $matiere) {
        $matiere_id = $matiere['id'];
        $notes_matiere = isset($notes[$matiere_id]) ? $notes[$matiere_id] : [];
        
        $total_notes = 0;
        $count_notes = 0;
        $note_classe = null;
        $note_examen = null;
        
        foreach ($notes_matiere as $note) {
            // Nouveau schéma: type_note ('classe' | 'examen'); Ancien schéma: session ('normale' | 'rattrapage')
            if (isset($note['type_note'])) {
                if ($note['type_note'] === 'classe') {
                    $note_classe = $note['note'];
                } elseif ($note['type_note'] === 'examen') {
                    $note_examen = $note['note'];
                }
            } else {
                if (isset($note['session']) && $note['session'] === 'normale') {
                    $note_classe = $note['note'];
                } elseif (isset($note['session'])) {
                    $note_examen = $note['note'];
                }
            }
            $total_notes += $note['note'];
            $count_notes++;
        }
        
        // Moyenne pondérée: 40% note de classe, 60% note d'examen
        $moyenne = null;
        if ($note_classe !== null && $note_examen !== null) {
            $moyenne = 0.4 * (float)$note_classe + 0.6 * (float)$note_examen;
        } elseif ($note_classe !== null) {
            $moyenne = (float)$note_classe;
        } elseif ($note_examen !== null) {
            $moyenne = (float)$note_examen;
        }
        
        $bulletin[] = [
            'matiere_id' => $matiere_id,
            'matiere_nom' => $matiere['matiere_nom'],
            'matiere_code' => $matiere['matiere_code'],
            'note_classe' => $note_classe,
            'note_examen' => $note_examen,
            'moyenne' => $moyenne,
            'mention' => $moyenne ? ($moyenne >= 16 ? 'Très Bien' : ($moyenne >= 14 ? 'Bien' : ($moyenne >= 12 ? 'Assez Bien' : ($moyenne >= 10 ? 'Passable' : 'Insuffisant')))) : 'Non évalué'
        ];
    }
    
} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des données: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Étudiant - <?php echo htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            --info-gradient: linear-gradient(135deg, #3498db 0%, #85c1e9 100%);
            --warning-gradient: linear-gradient(135deg, #f39c12 0%, #f7dc6f 100%);
            --danger-gradient: linear-gradient(135deg, #e74c3c 0%, #f1948a 100%);
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
            color: #667eea !important;
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
            background: var(--success-gradient);
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
            background: var(--primary-gradient);
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
            background: var(--primary-gradient);
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
            background: conic-gradient(#4CAF50 0deg 270deg, #e0e0e0 270deg 360deg);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .progress-ring::after {
            content: '75%';
            position: absolute;
            font-size: 0.8rem;
            font-weight: bold;
            color: #4CAF50;
        }


        .glass-effect {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .text-gradient {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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
                <?php if (!empty($etudiant['universite_logo_path'])): ?>
                    <img src="<?php echo htmlspecialchars($etudiant['universite_logo_path']); ?>" alt="Logo Université" style="height:36px; width:auto; object-fit:contain; margin-right:8px;">
                <?php else: ?>
                    <i class="fas fa-graduation-cap me-2"></i>
                <?php endif; ?>
                <div class="d-flex flex-column">
                    <strong>Espace Étudiant</strong>
                    <?php if (!empty($etudiant['universite_slogan'])): ?>
                        <small class="text-muted" style="line-height:1;"><?php echo htmlspecialchars($etudiant['universite_slogan']); ?></small>
                    <?php endif; ?>
                </div>
            </a>
            
            <div class="navbar-nav ms-auto d-flex align-items-center">
                
                
                <!-- User Menu -->
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                        <div class="user-avatar me-2">
                            <?php echo strtoupper(substr($etudiant['prenom'], 0, 1) . substr($etudiant['nom'], 0, 1)); ?>
                        </div>
                        <span class="d-none d-md-inline">
                            <?php echo htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']); ?>
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
                                    <i class="fas fa-sparkles me-2"></i>
                                    Bienvenue, <?php echo htmlspecialchars($etudiant['prenom']); ?> !
                                </h2>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="quick-stats glass-effect">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-id-card me-3 fs-4"></i>
                                                <div>
                                                    <small class="opacity-75">Numéro étudiant</small>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($etudiant['numero_etudiant']); ?></div>
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
                                    <i class="fas fa-user-graduate stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mes Matières -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="card-header" style="background: var(--info-gradient); color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-book me-2"></i>Mes Matières
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($matieres)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Aucune matière n'est encore assignée à votre classe.
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($matieres as $matiere): ?>
                                <div class="col-md-6">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body">
                                            <div class="d-flex align-items-start">
                                                <div class="flex-shrink-0">
                                                    <i class="fas fa-book-open text-primary fa-2x"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h6 class="card-title mb-1">
                                                        <?php echo htmlspecialchars($matiere['matiere_nom']); ?>
                                                        <?php if ($matiere['matiere_code']): ?>
                                                            <small class="text-muted">(<?php echo htmlspecialchars($matiere['matiere_code']); ?>)</small>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <div class="text-muted">
                                                        <i class="fas fa-user-tie me-1"></i>
                                                        <strong>Professeur:</strong><br>
                                                        <?php echo htmlspecialchars($matiere['prof_grade'] . ' ' . $matiere['prof_prenom'] . ' ' . $matiere['prof_nom']); ?>
                                                    </div>
                                                </div>
                                            </div>
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

        <!-- Mes Notes par Matière -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="card-header" style="background: var(--warning-gradient); color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>Mes Notes par Matière
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($matieres)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Aucune note disponible pour le moment.
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($matieres as $matiere): ?>
                                    <?php 
                                    $matiere_notes = isset($notes[$matiere['id']]) ? $notes[$matiere['id']] : [];
                                    $note_classe = null;
                                    $note_examen = null;
                                    
                                    foreach ($matiere_notes as $note) {
                                        if (isset($note['type_note'])) {
                                            if ($note['type_note'] === 'classe') {
                                                $note_classe = $note['note'];
                                            } elseif ($note['type_note'] === 'examen') {
                                                $note_examen = $note['note'];
                                            }
                                        } else {
                                            if (isset($note['session']) && $note['session'] === 'normale') {
                                                $note_classe = $note['note'];
                                            } elseif (isset($note['session'])) {
                                                $note_examen = $note['note'];
                                            }
                                        }
                                    }
                                    ?>
                                <div class="col-md-6">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body">
                                            <h6 class="card-title text-primary">
                                                <?php echo htmlspecialchars($matiere['matiere_nom']); ?>
                                                <?php if ($matiere['matiere_code']): ?>
                                                    <small class="text-muted">(<?php echo htmlspecialchars($matiere['matiere_code']); ?>)</small>
                                                <?php endif; ?>
                                            </h6>
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <div class="text-center p-2 bg-light rounded">
                                                        <div class="fw-bold text-info">
                                                            <?php echo $note_classe !== null ? number_format($note_classe, 2) : 'N/A'; ?>
                                                        </div>
                                                        <small class="text-muted">Note de classe</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="text-center p-2 bg-light rounded">
                                                        <div class="fw-bold text-success">
                                                            <?php echo $note_examen !== null ? number_format($note_examen, 2) : 'N/A'; ?>
                                                        </div>
                                                        <small class="text-muted">Note d'examen</small>
                                                    </div>
                                                </div>
                                            </div>
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

        <!-- Bulletin de Fin d'Année -->
        <div class="row">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="card-header d-flex align-items-center justify-content-between" style="background: var(--success-gradient); color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-certificate me-2"></i>Bulletin de Fin d'Année
                        </h5>
                        <a href="student_bulletin_print.php" class="btn btn-light btn-sm" target="_blank">
                            <i class="fas fa-print me-1"></i> Imprimer le bulletin
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($bulletin)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Aucune donnée disponible pour générer le bulletin.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Matière</th>
                                            <th class="text-center">Note de Classe</th>
                                            <th class="text-center">Note d'Examen</th>
                                            <th class="text-center">Moyenne</th>
                                            <th class="text-center">Mention</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_moyennes = 0;
                                        $count_moyennes = 0;
                                        foreach ($bulletin as $ligne): 
                                            if ($ligne['moyenne'] !== null) {
                                                $total_moyennes += $ligne['moyenne'];
                                                $count_moyennes++;
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($ligne['matiere_nom']); ?></strong>
                                                <?php if ($ligne['matiere_code']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($ligne['matiere_code']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($ligne['note_classe'] !== null): ?>
                                                    <span class="badge bg-info"><?php echo number_format($ligne['note_classe'], 2); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($ligne['note_examen'] !== null): ?>
                                                    <span class="badge bg-primary"><?php echo number_format($ligne['note_examen'], 2); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($ligne['moyenne'] !== null): ?>
                                                    <span class="badge <?php echo $ligne['moyenne'] >= 10 ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo number_format($ligne['moyenne'], 2); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php 
                                                $mention_class = '';
                                                switch($ligne['mention']) {
                                                    case 'Très Bien': $mention_class = 'bg-success'; break;
                                                    case 'Bien': $mention_class = 'bg-info'; break;
                                                    case 'Assez Bien': $mention_class = 'bg-warning'; break;
                                                    case 'Passable': $mention_class = 'bg-secondary'; break;
                                                    case 'Insuffisant': $mention_class = 'bg-danger'; break;
                                                    default: $mention_class = 'bg-light text-dark';
                                                }
                                                ?>
                                                <span class="badge <?php echo $mention_class; ?>">
                                                    <?php echo htmlspecialchars($ligne['mention']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th colspan="3">Moyenne Générale</th>
                                            <th class="text-center">
                                                <?php if ($count_moyennes > 0): ?>
                                                    <?php $moyenne_generale = $total_moyennes / $count_moyennes; ?>
                                                    <span class="badge <?php echo $moyenne_generale >= 10 ? 'bg-success' : 'bg-danger'; ?> fs-6">
                                                        <?php echo number_format($moyenne_generale, 2); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </th>
                                            <th class="text-center">
                                                <?php if ($count_moyennes > 0): ?>
                                                    <?php 
                                                    $mention_generale = $moyenne_generale >= 16 ? 'Très Bien' : 
                                                                       ($moyenne_generale >= 14 ? 'Bien' : 
                                                                       ($moyenne_generale >= 12 ? 'Assez Bien' : 
                                                                       ($moyenne_generale >= 10 ? 'Passable' : 'Insuffisant')));
                                                    $mention_class_generale = '';
                                                    switch($mention_generale) {
                                                        case 'Très Bien': $mention_class_generale = 'bg-success'; break;
                                                        case 'Bien': $mention_class_generale = 'bg-info'; break;
                                                        case 'Assez Bien': $mention_class_generale = 'bg-warning'; break;
                                                        case 'Passable': $mention_class_generale = 'bg-secondary'; break;
                                                        case 'Insuffisant': $mention_class_generale = 'bg-danger'; break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $mention_class_generale; ?> fs-6">
                                                        <?php echo $mention_generale; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
