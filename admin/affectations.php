<?php
require_once '../config.php';

// Vérification des permissions - universités et admins peuvent accéder
if (!isLoggedIn() || (!hasPermission('universite') && !hasPermission('admin'))) {
    redirect('../login.php');
}

$pdo = getDatabaseConnection();
$message = '';
$error = '';

// Contexte de scoping
$isAdmin = hasPermission('admin');
$isUniversite = hasPermission('universite');
$currentUniversiteId = $_SESSION['user_id'] ?? null;

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'affecter_etudiant') {
        $etudiant_id = (int)($_POST['etudiant_id'] ?? 0);
        $classe_id = (int)($_POST['classe_id'] ?? 0);
        
        if ($etudiant_id <= 0 || $classe_id <= 0) {
            $error = 'Veuillez sélectionner un étudiant et une classe';
        } else {
            try {
                if ($isUniversite && !$isAdmin) {
                    // Vérifier que l'étudiant appartient à une filière de l'université
                    $chkE = $pdo->prepare("SELECT 1 FROM etudiants e JOIN filieres f ON e.filiere_id = f.id JOIN universite_filiere uf ON uf.filiere_id = f.id WHERE e.id = ? AND uf.universite_id = ? LIMIT 1");
                    $chkE->execute([$etudiant_id, $currentUniversiteId]);
                    if (!$chkE->fetch()) {
                        throw new Exception("Action non autorisée: étudiant hors de votre université");
                    }
                    // Vérifier que la classe appartient à une filière de l'université
                    $chkC = $pdo->prepare("SELECT 1 FROM classes c JOIN filieres f ON c.filiere_id = f.id JOIN universite_filiere uf ON uf.filiere_id = f.id WHERE c.id = ? AND uf.universite_id = ? LIMIT 1");
                    $chkC->execute([$classe_id, $currentUniversiteId]);
                    if (!$chkC->fetch()) {
                        throw new Exception("Action non autorisée: classe hors de votre université");
                    }
                }
                $stmt = $pdo->prepare("UPDATE etudiants SET classe_id = ? WHERE id = ?");
                $stmt->execute([$classe_id, $etudiant_id]);
                $message = 'Étudiant affecté à la classe avec succès';
            } catch (PDOException $e) {
                $error = 'Erreur lors de l\'affectation de l\'étudiant';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'affecter_matiere_professeur') {
        $matiere_id = (int)($_POST['matiere_id'] ?? 0);
        $professeur_id = (int)($_POST['professeur_id'] ?? 0);
        
        if ($matiere_id <= 0 || $professeur_id <= 0) {
            $error = 'Veuillez sélectionner une matière et un professeur';
        } else {
            try {
                if ($isUniversite && !$isAdmin) {
                    // Vérifier que la matière appartient à l'université
                    $chkM = $pdo->prepare("SELECT 1 FROM matieres m JOIN filieres f ON m.filiere_id = f.id JOIN universite_filiere uf ON uf.filiere_id = f.id WHERE m.id = ? AND uf.universite_id = ? LIMIT 1");
                    $chkM->execute([$matiere_id, $currentUniversiteId]);
                    if (!$chkM->fetch()) {
                        throw new Exception("Action non autorisée: matière hors de votre université");
                    }
                    // Vérifier que le professeur est lié à l'université (au moins une matière de l'université)
                    $chkP = $pdo->prepare("SELECT 1 FROM professeurs p JOIN matiere_professeur mp ON mp.professeur_id = p.id JOIN matieres m ON m.id = mp.matiere_id JOIN filieres f ON f.id = m.filiere_id JOIN universite_filiere uf ON uf.filiere_id = f.id WHERE p.id = ? AND uf.universite_id = ? LIMIT 1");
                    $chkP->execute([$professeur_id, $currentUniversiteId]);
                    if (!$chkP->fetch()) {
                        throw new Exception("Action non autorisée: professeur hors de votre université");
                    }
                }
                // Vérifier si l'affectation existe déjà
                $stmt = $pdo->prepare("SELECT id FROM matiere_professeur WHERE matiere_id = ? AND professeur_id = ?");
                $stmt->execute([$matiere_id, $professeur_id]);
                if ($stmt->fetch()) {
                    $error = 'Cette affectation existe déjà';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO matiere_professeur (matiere_id, professeur_id) VALUES (?, ?)");
                    $stmt->execute([$matiere_id, $professeur_id]);
                    $message = 'Matière affectée au professeur avec succès';
                }
            } catch (PDOException $e) {
                $error = 'Erreur lors de l\'affectation de la matière';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'affecter_matiere_filiere') {
        $matiere_id = (int)($_POST['matiere_id'] ?? 0);
        $filiere_id = (int)($_POST['filiere_id'] ?? 0);
        
        if ($matiere_id <= 0 || $filiere_id <= 0) {
            $error = 'Veuillez sélectionner une matière et une filière';
        } else {
            try {
                if ($isUniversite && !$isAdmin) {
                    // Vérifier que matière et filière appartiennent à l'université
                    $chkM = $pdo->prepare("SELECT 1 FROM matieres m JOIN filieres f ON m.filiere_id = f.id JOIN universite_filiere uf ON uf.filiere_id = f.id WHERE m.id = ? AND uf.universite_id = ? LIMIT 1");
                    $chkM->execute([$matiere_id, $currentUniversiteId]);
                    if (!$chkM->fetch()) {
                        throw new Exception("Action non autorisée: matière hors de votre université");
                    }
                    $chkF = $pdo->prepare("SELECT 1 FROM filieres f JOIN universite_filiere uf ON uf.filiere_id = f.id WHERE f.id = ? AND uf.universite_id = ? LIMIT 1");
                    $chkF->execute([$filiere_id, $currentUniversiteId]);
                    if (!$chkF->fetch()) {
                        throw new Exception("Action non autorisée: filière hors de votre université");
                    }
                }
                // Vérifier si l'affectation existe déjà
                $stmt = $pdo->prepare("SELECT id FROM matiere_filiere WHERE matiere_id = ? AND filiere_id = ?");
                $stmt->execute([$matiere_id, $filiere_id]);
                if ($stmt->fetch()) {
                    $error = 'Cette affectation existe déjà';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO matiere_filiere (matiere_id, filiere_id) VALUES (?, ?)");
                    $stmt->execute([$matiere_id, $filiere_id]);
                    $message = 'Matière affectée à la filière avec succès';
                }
            } catch (PDOException $e) {
                $error = 'Erreur lors de l\'affectation de la matière';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'affecter_professeur_classe') {
        $professeur_id = (int)($_POST['professeur_id'] ?? 0);
        $classe_id = (int)($_POST['classe_id'] ?? 0);
        $matiere_ids = isset($_POST['matiere_ids']) ? array_filter(array_map('intval', (array)$_POST['matiere_ids'])) : [];
        
        if ($professeur_id <= 0 || $classe_id <= 0 || empty($matiere_ids)) {
            $error = 'Veuillez sélectionner un professeur, une classe et au moins une matière';
        } else {
            try {
                if ($isUniversite && !$isAdmin) {
                    // Vérifier appartenance professeur, classe et matières
                    $chkP = $pdo->prepare("SELECT 1 FROM professeurs p JOIN matiere_professeur mp ON mp.professeur_id = p.id JOIN matieres m ON m.id = mp.matiere_id JOIN filieres f ON f.id = m.filiere_id JOIN universite_filiere uf ON uf.filiere_id = f.id WHERE p.id = ? AND uf.universite_id = ? LIMIT 1");
                    $chkP->execute([$professeur_id, $currentUniversiteId]);
                    if (!$chkP->fetch()) throw new Exception("Action non autorisée: professeur hors de votre université");
                    $chkC = $pdo->prepare("SELECT 1 FROM classes c JOIN filieres f ON c.filiere_id = f.id JOIN universite_filiere uf ON uf.filiere_id = f.id WHERE c.id = ? AND uf.universite_id = ? LIMIT 1");
                    $chkC->execute([$classe_id, $currentUniversiteId]);
                    if (!$chkC->fetch()) throw new Exception("Action non autorisée: classe hors de votre université");
                    // Vérifier les matières
                    $inPh = implode(',', array_fill(0, count($matiere_ids), '?'));
                    $params = $matiere_ids; $params[] = $currentUniversiteId;
                    $chkM = $pdo->prepare("SELECT COUNT(DISTINCT m.id) FROM matieres m JOIN filieres f ON m.filiere_id = f.id JOIN universite_filiere uf ON uf.filiere_id = f.id WHERE m.id IN ($inPh) AND uf.universite_id = ?");
                    $chkM->execute($params);
                    if ((int)$chkM->fetchColumn() !== count($matiere_ids)) {
                        throw new Exception("Action non autorisée: matière(s) hors de votre université");
                    }
                }

                // Vérifier que chaque matière correspond à la filière de la classe (cohérence programme)
                $cf = $pdo->prepare("SELECT filiere_id FROM classes WHERE id = ?");
                $cf->execute([$classe_id]);
                $classFiliereId = (int)($cf->fetchColumn());
                if ($classFiliereId <= 0) {
                    throw new Exception("Classe invalide: filière introuvable");
                }
                $inPh2 = implode(',', array_fill(0, count($matiere_ids), '?'));
                $params2 = $matiere_ids; $params2[] = $classFiliereId;
                $chkSameF = $pdo->prepare("SELECT COUNT(DISTINCT m.id) FROM matieres m WHERE m.id IN ($inPh2) AND m.filiere_id = ?");
                $chkSameF->execute($params2);
                if ((int)$chkSameF->fetchColumn() !== count($matiere_ids)) {
                    throw new Exception("Chaque matière doit appartenir à la même filière que la classe");
                }
                // S'assurer que le lien professeur-classe existe
                $stmt = $pdo->prepare("SELECT id FROM professeur_classe WHERE professeur_id = ? AND classe_id = ?");
                $stmt->execute([$professeur_id, $classe_id]);
                if (!$stmt->fetch()) {
                    $insPC = $pdo->prepare("INSERT INTO professeur_classe (professeur_id, classe_id) VALUES (?, ?)");
                    $insPC->execute([$professeur_id, $classe_id]);
                }

                // Pour chaque matière sélectionnée, vérifier que la matière appartient au professeur
                $okCount = 0; $skipCount = 0; $invalidCount = 0; 
                foreach ($matiere_ids as $mid) {
                    // La matière doit être liée au professeur via matiere_professeur
                    $chkMP = $pdo->prepare("SELECT COUNT(*) FROM matiere_professeur WHERE professeur_id = ? AND matiere_id = ?");
                    $chkMP->execute([$professeur_id, $mid]);
                    if ((int)$chkMP->fetchColumn() === 0) {
                        $invalidCount++;
                        continue;
                    }

                    // Ne pas dupliquer dans affectations
                    $chkAff = $pdo->prepare("SELECT COUNT(*) FROM affectations WHERE professeur_id = ? AND classe_id = ? AND matiere_id = ?");
                    $chkAff->execute([$professeur_id, $classe_id, $mid]);
                    if ((int)$chkAff->fetchColumn() > 0) {
                        $skipCount++;
                        continue;
                    }

                    $insAff = $pdo->prepare("INSERT INTO affectations (classe_id, matiere_id, professeur_id) VALUES (?,?,?)");
                    $insAff->execute([$classe_id, $mid, $professeur_id]);
                    $okCount++;
                }

                if ($okCount > 0) {
                    $message = $okCount . ' matière(s) affectée(s) au professeur pour cette classe' . ($skipCount>0 ? ' (dont ' . $skipCount . ' déjà existante(s))' : '') . ($invalidCount>0 ? ' (' . $invalidCount . ' non autorisée(s) pour ce professeur)' : '');
                } else if ($skipCount > 0) {
                    $message = 'Toutes les matières sélectionnées étaient déjà affectées à ce professeur pour cette classe.';
                } else {
                    $error = 'Aucune affectation réalisée. Vérifiez les matières sélectionnées.';
                }
            } catch (PDOException $e) {
                $error = 'Erreur lors de l\'affectation du professeur/matières à la classe';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// Récupération des données pour les formulaires
$etudiants = [];
$classes = [];
$matieres = [];
$professeurs = [];
$filieres = [];

try {
    if ($isUniversite && !$isAdmin) {
        // Étudiants de l'université (via filière)
        $stmt = $pdo->prepare("
            SELECT e.id, e.nom, e.prenom, e.numero_etudiant, c.nom as classe_nom 
            FROM etudiants e 
            LEFT JOIN classes c ON e.classe_id = c.id 
            JOIN filieres f ON e.filiere_id = f.id
            JOIN universite_filiere uf ON uf.filiere_id = f.id
            WHERE uf.universite_id = ?
            ORDER BY e.nom, e.prenom
        ");
        $stmt->execute([$currentUniversiteId]);
        $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("
            SELECT e.id, e.nom, e.prenom, e.numero_etudiant, c.nom as classe_nom 
            FROM etudiants e 
            LEFT JOIN classes c ON e.classe_id = c.id 
            ORDER BY e.nom, e.prenom
        ");
        $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Classes
    if ($isUniversite && !$isAdmin) {
        $stmt = $pdo->prepare("
            SELECT c.id, c.nom, c.annee, c.semestre, f.nom as filiere_nom, GROUP_CONCAT(u.nom SEPARATOR ', ') as universites_noms
            FROM classes c 
            JOIN filieres f ON c.filiere_id = f.id 
            JOIN universite_filiere uf ON f.id = uf.filiere_id
            JOIN universites u ON uf.universite_id = u.id 
            WHERE uf.universite_id = ?
            GROUP BY c.id, c.nom, c.annee, c.semestre, f.nom
            ORDER BY c.annee DESC, c.semestre, c.nom
        ");
        $stmt->execute([$currentUniversiteId]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("
            SELECT c.id, c.nom, c.annee, c.semestre, f.nom as filiere_nom, GROUP_CONCAT(u.nom SEPARATOR ', ') as universites_noms
            FROM classes c 
            LEFT JOIN filieres f ON c.filiere_id = f.id 
            LEFT JOIN universite_filiere uf ON f.id = uf.filiere_id
            LEFT JOIN universites u ON uf.universite_id = u.id 
            GROUP BY c.id, c.nom, c.annee, c.semestre, f.nom
            ORDER BY c.annee DESC, c.semestre, c.nom
        ");
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Matières
    if ($isUniversite && !$isAdmin) {
        $stmt = $pdo->prepare("
            SELECT m.id, m.nom, m.code, f.nom as filiere_nom, GROUP_CONCAT(u.nom SEPARATOR ', ') as universites_noms
            FROM matieres m 
            JOIN filieres f ON m.filiere_id = f.id 
            JOIN universite_filiere uf ON f.id = uf.filiere_id
            JOIN universites u ON uf.universite_id = u.id 
            WHERE uf.universite_id = ?
            GROUP BY m.id, m.nom, m.code, f.nom
            ORDER BY m.nom
        ");
        $stmt->execute([$currentUniversiteId]);
        $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("
            SELECT m.id, m.nom, m.code, f.nom as filiere_nom, GROUP_CONCAT(u.nom SEPARATOR ', ') as universites_noms
            FROM matieres m 
            LEFT JOIN filieres f ON m.filiere_id = f.id 
            LEFT JOIN universite_filiere uf ON f.id = uf.filiere_id
            LEFT JOIN universites u ON uf.universite_id = u.id 
            GROUP BY m.id, m.nom, m.code, f.nom
            ORDER BY m.nom
        ");
        $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Professeurs avec agrégation de leurs matières
    if ($isUniversite && !$isAdmin) {
        $stmt = $pdo->prepare("
            SELECT p.id, p.nom, p.prenom, p.grade,
                   mm.matieres_noms, mm.matiere_ids_csv
            FROM professeurs p
            JOIN matiere_professeur mp ON mp.professeur_id = p.id
            JOIN matieres m ON m.id = mp.matiere_id
            JOIN filieres f ON f.id = m.filiere_id
            JOIN universite_filiere uf ON uf.filiere_id = f.id
            LEFT JOIN (
                SELECT mp2.professeur_id,
                       GROUP_CONCAT(m2.nom ORDER BY m2.nom SEPARATOR ', ') AS matieres_noms,
                       GROUP_CONCAT(m2.id ORDER BY m2.nom SEPARATOR ',') AS matiere_ids_csv
                FROM matiere_professeur mp2
                JOIN matieres m2 ON m2.id = mp2.matiere_id
                GROUP BY mp2.professeur_id
            ) mm ON mm.professeur_id = p.id
            WHERE uf.universite_id = ?
            GROUP BY p.id, p.nom, p.prenom, p.grade
            ORDER BY p.nom, p.prenom
        ");
        $stmt->execute([$currentUniversiteId]);
        $professeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("
            SELECT p.id, p.nom, p.prenom, p.grade,
                   mm.matieres_noms, mm.matiere_ids_csv
            FROM professeurs p
            LEFT JOIN (
                SELECT mp.professeur_id,
                       GROUP_CONCAT(m.nom ORDER BY m.nom SEPARATOR ', ') AS matieres_noms,
                       GROUP_CONCAT(m.id ORDER BY m.nom SEPARATOR ',') AS matiere_ids_csv
                FROM matiere_professeur mp
                JOIN matieres m ON m.id = mp.matiere_id
                GROUP BY mp.professeur_id
            ) mm ON mm.professeur_id = p.id
            ORDER BY p.nom, p.prenom
        ");
        $professeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Filières
    if ($isUniversite && !$isAdmin) {
        $stmt = $pdo->prepare("
            SELECT f.id, f.nom, f.niveau_entree, GROUP_CONCAT(u.nom SEPARATOR ', ') as universites_noms
            FROM filieres f 
            JOIN universite_filiere uf ON f.id = uf.filiere_id
            JOIN universites u ON uf.universite_id = u.id 
            WHERE uf.universite_id = ?
            GROUP BY f.id, f.nom, f.niveau_entree
            ORDER BY f.nom
        ");
        $stmt->execute([$currentUniversiteId]);
        $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("
            SELECT f.id, f.nom, f.niveau_entree, GROUP_CONCAT(u.nom SEPARATOR ', ') as universites_noms
            FROM filieres f 
            LEFT JOIN universite_filiere uf ON f.id = uf.filiere_id
            LEFT JOIN universites u ON uf.universite_id = u.id 
            GROUP BY f.id, f.nom, f.niveau_entree
            ORDER BY f.nom
        ");
        $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Préparer une map des matières (id => {id, nom, code})
    $matiereMap = [];
    foreach ($matieres as $m) {
        $matiereMap[(string)$m['id']] = [
            'id' => (int)$m['id'],
            'nom' => $m['nom'],
            'code' => $m['code']
        ];
    }
    // Construire un dictionnaire des matières par professeur pour le JS
    $matieresByProf = [];
    foreach ($professeurs as $p) {
        $pid = (string)$p['id'];
        $matieresByProf[$pid] = [];
        if (!empty($p['matiere_ids_csv'])) {
            foreach (explode(',', $p['matiere_ids_csv']) as $mid) {
                $mid = trim($mid);
                if (isset($matiereMap[$mid])) {
                    $matieresByProf[$pid][] = $matiereMap[$mid];
                }
            }
        }
    }
    
} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des données';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Affectations - Interface Université</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .admin-container {
            padding: 20px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        .card {
            border: none;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 15px;
        }
        .btn-action {
            margin: 2px;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        .assignment-section {
            border-left: 4px solid #0d6efd;
            padding-left: 15px;
            margin-bottom: 30px;
        }
        .form-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="container-fluid">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2><i class="fas fa-link me-2"></i>Gestion des Affectations - Interface Université</h2>
                        <a href="<?php echo $isAdmin ? 'dashboard.php' : 'universite_dashboard.php'; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Retour au Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Affectation Étudiants aux Classes -->
            <div class="assignment-section">
                <h3 class="mb-4"><i class="fas fa-user-graduate me-2"></i>Affectation des Étudiants aux Classes</h3>
                
                <div class="form-section">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="affecter_etudiant">
                        
                        <div class="col-md-6">
                            <label for="etudiant_id" class="form-label">Étudiant *</label>
                            <select class="form-select" id="etudiant_id" name="etudiant_id" required>
                                <option value="">Sélectionner un étudiant</option>
                                <?php foreach ($etudiants as $etudiant): ?>
                                    <option value="<?php echo $etudiant['id']; ?>">
                                        <?php echo htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']); ?> 
                                        (<?php echo htmlspecialchars($etudiant['numero_etudiant']); ?>)
                                        <?php if ($etudiant['classe_nom']): ?>
                                            - Actuellement: <?php echo htmlspecialchars($etudiant['classe_nom']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="classe_id" class="form-label">Classe *</label>
                            <select class="form-select" id="classe_id" name="classe_id" required>
                                <option value="">Sélectionner une classe</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>">
                                        <?php echo htmlspecialchars($classe['nom']); ?> 
                                        (<?php echo htmlspecialchars($classe['annee']); ?> - 
                                        <?php echo htmlspecialchars($classe['semestre']); ?> - 
                                        <?php echo htmlspecialchars($classe['filiere_nom']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-light">
                                <i class="fas fa-save me-2"></i>Affecter l'étudiant
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Affectation Matières aux Professeurs -->
            <div class="assignment-section">
                <h3 class="mb-4"><i class="fas fa-chalkboard-teacher me-2"></i>Affectation des Matières aux Professeurs</h3>
                
                <div class="form-section">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="affecter_matiere_professeur">
                        
                        <div class="col-md-6">
                            <label for="matiere_id_prof" class="form-label">Matière *</label>
                            <select class="form-select" id="matiere_id_prof" name="matiere_id" required>
                                <option value="">Sélectionner une matière</option>
                                <?php foreach ($matieres as $matiere): ?>
                                    <option value="<?php echo $matiere['id']; ?>">
                                        <?php echo htmlspecialchars($matiere['nom']); ?> 
                                        (<?php echo htmlspecialchars($matiere['code']); ?> - 
                                        <?php echo htmlspecialchars($matiere['filiere_nom']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="professeur_id_mat" class="form-label">Professeur *</label>
                            <select class="form-select" id="professeur_id_mat" name="professeur_id" required>
                                <option value="">Sélectionner un professeur</option>
                                <?php foreach ($professeurs as $professeur): ?>
                                    <option value="<?php echo $professeur['id']; ?>">
                                        <?php echo htmlspecialchars($professeur['nom'] . ' ' . $professeur['prenom']); ?> 
                                        (<?php echo htmlspecialchars($professeur['grade'] ?? 'N/A'); ?> - 
                                        <?php echo htmlspecialchars($professeur['matieres_noms'] ?? 'Aucune matière'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-light">
                                <i class="fas fa-save me-2"></i>Affecter la matière
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Affectation Matières aux Filières -->
            <div class="assignment-section">
                <h3 class="mb-4"><i class="fas fa-sitemap me-2"></i>Affectation des Matières aux Filières</h3>
                
                <div class="form-section">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="affecter_matiere_filiere">
                        
                        <div class="col-md-6">
                            <label for="matiere_id_fil" class="form-label">Matière *</label>
                            <select class="form-select" id="matiere_id_fil" name="matiere_id" required>
                                <option value="">Sélectionner une matière</option>
                                <?php foreach ($matieres as $matiere): ?>
                                    <option value="<?php echo $matiere['id']; ?>">
                                        <?php echo htmlspecialchars($matiere['nom']); ?> 
                                        (<?php echo htmlspecialchars($matiere['code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="filiere_id_mat" class="form-label">Filière *</label>
                            <select class="form-select" id="filiere_id_mat" name="filiere_id" required>
                                <option value="">Sélectionner une filière</option>
                                <?php foreach ($filieres as $filiere): ?>
                                    <option value="<?php echo $filiere['id']; ?>">
                                        <?php echo htmlspecialchars($filiere['nom']); ?> 
                                        (<?php echo htmlspecialchars($filiere['niveau_entree']); ?> - 
                                        <?php echo htmlspecialchars($filiere['universites_noms'] ?? 'N/A'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-light">
                                <i class="fas fa-save me-2"></i>Affecter la matière
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Affectation Professeurs aux Classes -->
            <div class="assignment-section">
                <h3 class="mb-4"><i class="fas fa-users me-2"></i>Affectation des Professeurs aux Classes</h3>
                
                <div class="form-section">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="affecter_professeur_classe">
                        
                        <div class="col-md-6">
                            <label for="professeur_id_cla" class="form-label">Professeur *</label>
                            <select class="form-select" id="professeur_id_cla" name="professeur_id" required>
                                <option value="">Sélectionner un professeur</option>
                                <?php foreach ($professeurs as $professeur): ?>
                                    <option value="<?php echo $professeur['id']; ?>">
                                        <?php echo htmlspecialchars($professeur['nom'] . ' ' . $professeur['prenom']); ?> 
                                        (<?php echo htmlspecialchars($professeur['grade'] ?? 'N/A'); ?> - 
                                        <?php echo htmlspecialchars($professeur['matieres_noms'] ?? 'Aucune matière'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="classe_id_prof" class="form-label">Classe *</label>
                            <select class="form-select" id="classe_id_prof" name="classe_id" required>
                                <option value="">Sélectionner une classe</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>">
                                        <?php echo htmlspecialchars($classe['nom']); ?> 
                                        (<?php echo htmlspecialchars($classe['annee']); ?> - 
                                        <?php echo htmlspecialchars($classe['semestre']); ?> - 
                                        <?php echo htmlspecialchars($classe['filiere_nom']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="matiere_ids_prof" class="form-label">Matière(s) à enseigner dans cette classe *</label>
                            <select class="form-select" id="matiere_ids_prof" name="matiere_ids[]" multiple size="6" required disabled>
                                <!-- Options injectées dynamiquement selon le professeur sélectionné -->
                            </select>
                            <div class="form-text text-light">Astuce: maintenez Ctrl (Windows) ou Cmd (Mac) pour sélectionner plusieurs matières.</div>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-light">
                                <i class="fas fa-save me-2"></i>Affecter le professeur
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Informations sur les affectations -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations sur les Affectations</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-user-graduate me-2"></i>Étudiants par Classe</h6>
                                    <ul class="list-unstyled">
                                        <?php foreach ($classes as $classe): ?>
                                            <li class="mb-2">
                                                <strong><?php echo htmlspecialchars($classe['nom']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php 
                                                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM etudiants WHERE classe_id = ?");
                                                    $stmt->execute([$classe['id']]);
                                                    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                                    ?>
                                                    <?php echo $count; ?> étudiant(s)
                                                </small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-chalkboard-teacher me-2"></i>Matières par Professeur</h6>
                                    <ul class="list-unstyled">
                                        <?php foreach ($professeurs as $professeur): ?>
                                            <li class="mb-2">
                                                <strong><?php echo htmlspecialchars($professeur['nom'] . ' ' . $professeur['prenom']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php 
                                                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM matiere_professeur WHERE professeur_id = ?");
                                                    $stmt->execute([$professeur['id']]);
                                                    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                                    ?>
                                                    <?php echo $count; ?> matière(s)
                                                </small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Données des matières par professeur (générées côté serveur)
        const MATIERES_BY_PROF = <?php echo json_encode($matieresByProf, JSON_UNESCAPED_UNICODE); ?>;
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Validation des formulaires
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            isValid = false;
                            field.classList.add('is-invalid');
                        } else {
                            field.classList.remove('is-invalid');
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('Veuillez remplir tous les champs obligatoires');
                    }
                });
            });

            // Peupler la liste des matières selon le professeur sélectionné
            const profSelect = document.getElementById('professeur_id_cla');
            const matSelect = document.getElementById('matiere_ids_prof');

            function populateMatieresForProf(pid) {
                // vider
                while (matSelect.options.length > 0) matSelect.remove(0);
                const list = MATIERES_BY_PROF[String(pid)] || [];
                if (!pid || list.length === 0) {
                    matSelect.disabled = true;
                    return;
                }
                list.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    opt.textContent = m.nom + (m.code ? ' (' + m.code + ')' : '');
                    matSelect.appendChild(opt);
                });
                matSelect.disabled = false;
            }

            profSelect.addEventListener('change', function(){
                populateMatieresForProf(this.value);
            });

            // Initialisation si un professeur est déjà sélectionné
            if (profSelect && profSelect.value) {
                populateMatieresForProf(profSelect.value);
            }
        });
    </script>
</body>
</html> 