<?php
require_once '../config.php';
require_once '../includes/user_accounts.php';

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
    
    if ($action === 'create') {
        $nom = sanitizeInput($_POST['nom'] ?? '');
        $prenom = sanitizeInput($_POST['prenom'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $telephone = sanitizeInput($_POST['telephone'] ?? '');
        $date_naissance = sanitizeInput($_POST['date_naissance'] ?? '');
        $adresse = sanitizeInput($_POST['adresse'] ?? '');
        $numero_etudiant = sanitizeInput($_POST['numero_etudiant'] ?? '');
        $classe_id = (int)($_POST['classe_id'] ?? 0);
        $filiere_id = (int)($_POST['filiere_id'] ?? 0);
        
        if (empty($nom) || empty($prenom) || empty($email) || empty($numero_etudiant) || $classe_id <= 0) {
            $error = 'Veuillez remplir tous les champs obligatoires';
        } else {
            try {
                // Pour les universités, vérifier la propriété de la classe et de la filière
                if ($isUniversite && !$isAdmin) {
                    // Vérifier que la classe appartient à une filière liée à l'université
                    $checkClasse = $pdo->prepare("SELECT c.id, c.filiere_id FROM classes c JOIN filieres f ON c.filiere_id = f.id JOIN universite_filiere uf ON uf.filiere_id = f.id WHERE c.id = ? AND uf.universite_id = ?");
                    $checkClasse->execute([$classe_id, $currentUniversiteId]);
                    $classeRow = $checkClasse->fetch(PDO::FETCH_ASSOC);
                    if (!$classeRow) {
                        throw new Exception("Action non autorisée: classe hors de votre université");
                    }
                    // Vérifier que la filière sélectionnée appartient aussi à l'université et correspond à la classe
                    $checkFiliere = $pdo->prepare("SELECT 1 FROM universite_filiere WHERE filiere_id = ? AND universite_id = ?");
                    $checkFiliere->execute([$filiere_id, $currentUniversiteId]);
                    if (!$checkFiliere->fetch()) {
                        throw new Exception("Action non autorisée: filière hors de votre université");
                    }
                    if ((int)$classeRow['filiere_id'] !== (int)$filiere_id) {
                        throw new Exception("Incohérence: la classe choisie n'appartient pas à la filière sélectionnée");
                    }
                }

                $stmt = $pdo->prepare("INSERT INTO etudiants (nom, prenom, email, telephone, date_naissance, adresse, numero_etudiant, classe_id, filiere_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nom, $prenom, $email, $telephone, $date_naissance, $adresse, $numero_etudiant, $classe_id, $filiere_id]);
                $message = 'Étudiant créé avec succès';
            } catch (PDOException $e) {
                $error = 'Erreur lors de la création de l\'étudiant';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $nom = sanitizeInput($_POST['nom'] ?? '');
        $prenom = sanitizeInput($_POST['prenom'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $telephone = sanitizeInput($_POST['telephone'] ?? '');
        $date_naissance = sanitizeInput($_POST['date_naissance'] ?? '');
        $adresse = sanitizeInput($_POST['adresse'] ?? '');
        $numero_etudiant = sanitizeInput($_POST['numero_etudiant'] ?? '');
        $classe_id = (int)($_POST['classe_id'] ?? 0);
        $filiere_id = (int)($_POST['filiere_id'] ?? 0);
        
        if ($id <= 0 || empty($nom) || empty($prenom) || empty($email) || empty($numero_etudiant) || $classe_id <= 0) {
            $error = 'Données invalides';
        } else {
            try {
                // Vérifier la propriété de l'étudiant et la cohérence des nouvelles références pour les universités
                if ($isUniversite && !$isAdmin) {
                    // L'étudiant doit appartenir à l'université
                    $checkStudent = $pdo->prepare("SELECT e.id FROM etudiants e JOIN classes c ON e.classe_id = c.id JOIN filieres f ON c.filiere_id = f.id JOIN universite_filiere uf ON uf.filiere_id = f.id WHERE e.id = ? AND uf.universite_id = ?");
                    $checkStudent->execute([$id, $currentUniversiteId]);
                    if (!$checkStudent->fetch()) {
                        throw new Exception("Action non autorisée: étudiant hors de votre université");
                    }
                    // Classe et filière ciblées doivent appartenir à l'université et être cohérentes
                    $checkClasse = $pdo->prepare("SELECT c.id, c.filiere_id FROM classes c JOIN filieres f ON c.filiere_id = f.id JOIN universite_filiere uf ON uf.filiere_id = f.id WHERE c.id = ? AND uf.universite_id = ?");
                    $checkClasse->execute([$classe_id, $currentUniversiteId]);
                    $classeRow = $checkClasse->fetch(PDO::FETCH_ASSOC);
                    if (!$classeRow) {
                        throw new Exception("Action non autorisée: classe hors de votre université");
                    }
                    $checkFiliere = $pdo->prepare("SELECT 1 FROM universite_filiere WHERE filiere_id = ? AND universite_id = ?");
                    $checkFiliere->execute([$filiere_id, $currentUniversiteId]);
                    if (!$checkFiliere->fetch()) {
                        throw new Exception("Action non autorisée: filière hors de votre université");
                    }
                    if ((int)$classeRow['filiere_id'] !== (int)$filiere_id) {
                        throw new Exception("Incohérence: la classe choisie n'appartient pas à la filière sélectionnée");
                    }
                }

                $stmt = $pdo->prepare("UPDATE etudiants SET nom = ?, prenom = ?, email = ?, telephone = ?, date_naissance = ?, adresse = ?, numero_etudiant = ?, classe_id = ?, filiere_id = ? WHERE id = ?");
                $stmt->execute([$nom, $prenom, $email, $telephone, $date_naissance, $adresse, $numero_etudiant, $classe_id, $filiere_id, $id]);
                $message = 'Étudiant mis à jour avec succès';
            } catch (PDOException $e) {
                $error = 'Erreur lors de la mise à jour de l\'étudiant';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id > 0) {
            try {
                if ($isUniversite && !$isAdmin) {
                    // Vérifier que l'étudiant appartient à l'université avant suppression
                    $checkStudent = $pdo->prepare("SELECT e.id FROM etudiants e JOIN classes c ON e.classe_id = c.id JOIN filieres f ON c.filiere_id = f.id JOIN universite_filiere uf ON uf.filiere_id = f.id WHERE e.id = ? AND uf.universite_id = ?");
                    $checkStudent->execute([$id, $currentUniversiteId]);
                    if (!$checkStudent->fetch()) {
                        throw new Exception("Action non autorisée: étudiant hors de votre université");
                    }
                }
                $stmt = $pdo->prepare("DELETE FROM etudiants WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Étudiant supprimé avec succès';
            } catch (PDOException $e) {
                $error = 'Erreur lors de la suppression de l\'étudiant';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'create_account') {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id > 0) {
            // Vérifier la propriété avant de créer le compte
            if ($isUniversite && !$isAdmin) {
                $checkStudent = $pdo->prepare("SELECT e.id FROM etudiants e JOIN classes c ON e.classe_id = c.id JOIN filieres f ON c.filiere_id = f.id JOIN universite_filiere uf ON uf.filiere_id = f.id WHERE e.id = ? AND uf.universite_id = ?");
                $checkStudent->execute([$id, $currentUniversiteId]);
                if (!$checkStudent->fetch()) {
                    $error = "Action non autorisée: étudiant hors de votre université";
                }
            }

            if (!$error) {
                $result = createStudentAccount($pdo, $id);
                if ($result['success']) {
                    $message = "COMPTE_CREE";
                    $password_info = [
                        'nom' => $result['nom'],
                        'prenom' => $result['prenom'],
                        'email' => $result['email'],
                        'password' => $result['password']
                    ];
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}

// Récupération des étudiants
$etudiants = [];
try {
    if ($isUniversite && !$isAdmin) {
        $stmt = $pdo->prepare("
            SELECT e.*, c.nom as classe_nom, c.annee, c.semestre, f.nom as filiere_nom,
                   GROUP_CONCAT(DISTINCT u.nom SEPARATOR ', ') as universites_noms,
                   CASE WHEN e.mot_de_passe IS NOT NULL THEN 1 ELSE 0 END as has_account
            FROM etudiants e
            JOIN classes c ON e.classe_id = c.id
            JOIN filieres f ON e.filiere_id = f.id
            JOIN universite_filiere uf ON f.id = uf.filiere_id
            JOIN universites u ON uf.universite_id = u.id
            WHERE uf.universite_id = ?
            GROUP BY e.id, c.nom, c.annee, c.semestre, f.nom
            ORDER BY e.nom, e.prenom
        ");
        $stmt->execute([$currentUniversiteId]);
        $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("
            SELECT e.*, c.nom as classe_nom, c.annee, c.semestre, f.nom as filiere_nom,
                   GROUP_CONCAT(DISTINCT u.nom SEPARATOR ', ') as universites_noms,
                   CASE WHEN e.mot_de_passe IS NOT NULL THEN 1 ELSE 0 END as has_account
            FROM etudiants e 
            LEFT JOIN classes c ON e.classe_id = c.id 
            LEFT JOIN filieres f ON e.filiere_id = f.id 
            LEFT JOIN universite_filiere uf ON f.id = uf.filiere_id
            LEFT JOIN universites u ON uf.universite_id = u.id 
            GROUP BY e.id, c.nom, c.annee, c.semestre, f.nom
            ORDER BY e.nom, e.prenom
        ");
        $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des étudiants';
}

// Récupération des classes pour le formulaire
$classes = [];
try {
    if ($isUniversite && !$isAdmin) {
        $stmt = $pdo->prepare("
            SELECT c.id, c.nom, c.annee, c.semestre, f.nom as filiere_nom,
                   GROUP_CONCAT(DISTINCT u.nom SEPARATOR ', ') as universites_noms
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
} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des classes';
}

// Récupération des filières pour le formulaire
$filieres = [];
try {
    if ($isUniversite && !$isAdmin) {
        $stmt = $pdo->prepare("
            SELECT f.id, f.nom, f.niveau_entree, GROUP_CONCAT(DISTINCT u.nom SEPARATOR ', ') as universites_noms
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
} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des filières: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Étudiants - Interface Université</title>
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
        .student-number {
            font-family: monospace;
            font-weight: bold;
        }
        .age-badge {
            font-size: 0.7em;
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
                        <h2><i class="fas fa-user-graduate me-2"></i>Gestion des Étudiants - Interface Université</h2>
                        <a href="<?php echo $isAdmin ? 'dashboard.php' : 'universite_dashboard.php'; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Retour au Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if (isset($password_info)): ?>
                <div class="alert alert-success border-0 shadow-lg" role="alert" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <div class="d-flex align-items-center mb-3">
                        <i class="fas fa-user-check fa-2x text-white me-3"></i>
                        <div>
                            <h5 class="text-white mb-0">✅ Compte créé avec succès !</h5>
                            <small class="text-white-50">Pour <?php echo htmlspecialchars($password_info['prenom'] . ' ' . $password_info['nom']); ?></small>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="bg-white bg-opacity-20 rounded p-3">
                                <label class="text-white-50 small">EMAIL DE CONNEXION</label>
                                <div class="d-flex align-items-center">
                                    <code class="bg-white text-dark px-2 py-1 rounded me-2 flex-grow-1" style="font-size: 1.1em;"><?php echo htmlspecialchars($password_info['email']); ?></code>
                                    <button type="button" class="btn btn-light btn-sm" onclick="copyToClipboard('<?php echo htmlspecialchars($password_info['email']); ?>')" title="Copier l'email">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="bg-white bg-opacity-20 rounded p-3">
                                <label class="text-white-50 small">MOT DE PASSE TEMPORAIRE</label>
                                <div class="d-flex align-items-center">
                                    <code class="bg-warning text-dark px-2 py-1 rounded me-2 flex-grow-1" style="font-size: 1.2em; font-weight: bold;"><?php echo htmlspecialchars($password_info['password']); ?></code>
                                    <button type="button" class="btn btn-warning btn-sm" onclick="copyToClipboard('<?php echo htmlspecialchars($password_info['password']); ?>')" title="Copier le mot de passe">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3 p-3 bg-white bg-opacity-10 rounded">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-info-circle text-white me-2 mt-1"></i>
                            <div class="text-white small">
                                <strong>Instructions importantes :</strong><br>
                                • Communiquez ces informations à l'étudiant de manière sécurisée<br>
                                • L'étudiant devra changer son mot de passe lors de sa première connexion<br>
                                • Page de connexion : <code class="bg-white text-dark px-1 rounded">student_login.php</code>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-light" onclick="printCredentials()">
                            <i class="fas fa-print me-2"></i>Imprimer les identifiants
                        </button>
                        <button type="button" class="btn btn-outline-light ms-2" onclick="this.parentElement.parentElement.style.display='none'">
                            <i class="fas fa-times me-2"></i>Fermer (après avoir noté)
                        </button>
                    </div>
                </div>
            <?php elseif ($message && $message !== 'COMPTE_CREE'): ?>
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

            <!-- Formulaire de création -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Nouvel Étudiant</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="action" value="create">
                                
                                <div class="col-md-6">
                                    <label for="nom" class="form-label">Nom *</label>
                                    <input type="text" class="form-control" id="nom" name="nom" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="prenom" class="form-label">Prénom *</label>
                                    <input type="text" class="form-control" id="prenom" name="prenom" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="telephone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="telephone" name="telephone">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="date_naissance" class="form-label">Date de naissance</label>
                                    <input type="date" class="form-control" id="date_naissance" name="date_naissance">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="numero_etudiant" class="form-label">Numéro étudiant *</label>
                                    <input type="text" class="form-control" id="numero_etudiant" name="numero_etudiant" required>
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
                                
                                <div class="col-md-6">
                                    <label for="filiere_id" class="form-label">Filière *</label>
                                    <select class="form-select" id="filiere_id" name="filiere_id" required>
                                        <option value="">Sélectionner une filière</option>
                                        <?php foreach ($filieres as $filiere): ?>
                                            <option value="<?php echo $filiere['id']; ?>">
                                                <?php echo htmlspecialchars($filiere['nom']); ?> 
                                                (<?php echo htmlspecialchars($filiere['niveau_entree']); ?> - 
                                                <?php echo htmlspecialchars($filiere['universites_noms']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <label for="adresse" class="form-label">Adresse</label>
                                    <textarea class="form-control" id="adresse" name="adresse" rows="2"></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Créer l'étudiant
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste des étudiants -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Liste des Étudiants</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Numéro</th>
                                            <th>Nom</th>
                                            <th>Email</th>
                                            <th>Téléphone</th>
                                            <th>Âge</th>
                                            <th>Classe</th>
                                            <th>Filière</th>
                                            <th>Compte</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($etudiants as $etudiant): ?>
                                            <tr>
                                                <td><?php echo $etudiant['id']; ?></td>
                                                <td>
                                                    <span class="badge bg-primary student-number">
                                                        <?php echo htmlspecialchars($etudiant['numero_etudiant']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']); ?></strong>
                                                </td>
                                                <td>
                                                    <a href="mailto:<?php echo htmlspecialchars($etudiant['email']); ?>">
                                                        <?php echo htmlspecialchars($etudiant['email']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php if ($etudiant['telephone']): ?>
                                                        <a href="tel:<?php echo htmlspecialchars($etudiant['telephone']); ?>">
                                                            <?php echo htmlspecialchars($etudiant['telephone']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($etudiant['date_naissance']): ?>
                                                        <?php 
                                                        $date_naissance = new DateTime($etudiant['date_naissance']);
                                                        $aujourd_hui = new DateTime();
                                                        $age = $aujourd_hui->diff($date_naissance)->y;
                                                        ?>
                                                        <span class="badge bg-secondary age-badge">
                                                            <?php echo $age; ?> ans
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small>
                                                        <?php echo htmlspecialchars($etudiant['classe_nom'] ?? 'N/A'); ?>
                                                        <br>
                                                        <em class="text-muted">
                                                            <?php echo htmlspecialchars($etudiant['annee'] ?? ''); ?> - 
                                                            <?php echo htmlspecialchars($etudiant['semestre'] ?? ''); ?>
                                                        </em>
                                                    </small>
                                                </td>
                                                <td>
                                                    <small>
                                                        <?php echo htmlspecialchars($etudiant['filiere_nom'] ?? 'N/A'); ?>
                                                        <br>
                                                        <em class="text-muted">
                                                            <?php echo htmlspecialchars($etudiant['universites_noms'] ?? ''); ?>
                                                        </em>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($etudiant['has_account']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check me-1"></i>Actif
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">
                                                            <i class="fas fa-times me-1"></i>Aucun
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary btn-action" 
                                                            onclick="editEtudiant(<?php echo htmlspecialchars(json_encode($etudiant)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if (!$etudiant['has_account'] && !empty($etudiant['email'])): ?>
                                                        <button class="btn btn-sm btn-outline-success btn-action" 
                                                                onclick="createAccount(<?php echo $etudiant['id']; ?>, '<?php echo htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']); ?>')" 
                                                                title="Créer un compte">
                                                            <i class="fas fa-user-plus"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-outline-danger btn-action" 
                                                            onclick="deleteEtudiant(<?php echo $etudiant['id']; ?>, '<?php echo htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal d'édition -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Modifier l'Étudiant</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_nom" class="form-label">Nom *</label>
                                <input type="text" class="form-control" id="edit_nom" name="nom" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="edit_prenom" class="form-label">Prénom *</label>
                                <input type="text" class="form-control" id="edit_prenom" name="prenom" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="edit_email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="edit_telephone" class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" id="edit_telephone" name="telephone">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="edit_date_naissance" class="form-label">Date de naissance</label>
                                <input type="date" class="form-control" id="edit_date_naissance" name="date_naissance">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="edit_numero_etudiant" class="form-label">Numéro étudiant *</label>
                                <input type="text" class="form-control" id="edit_numero_etudiant" name="numero_etudiant" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="edit_classe_id" class="form-label">Classe *</label>
                                <select class="form-select" id="edit_classe_id" name="classe_id" required>
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
                            
                            <div class="col-md-6">
                                <label for="edit_filiere_id" class="form-label">Filière *</label>
                                <select class="form-select" id="edit_filiere_id" name="filiere_id" required>
                                    <option value="">Sélectionner une filière</option>
                                    <?php foreach ($filieres as $filiere): ?>
                                        <option value="<?php echo $filiere['id']; ?>">
                                            <?php echo htmlspecialchars($filiere['nom']); ?> 
                                            (<?php echo htmlspecialchars($filiere['niveau_entree']); ?> - 
                                            <?php echo htmlspecialchars($filiere['universites_noms']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label for="edit_adresse" class="form-label">Adresse</label>
                                <textarea class="form-control" id="edit_adresse" name="adresse" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formulaire de suppression -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
    </form>

    <!-- Formulaire de création de compte -->
    <form method="POST" id="createAccountForm" style="display: none;">
        <input type="hidden" name="action" value="create_account">
        <input type="hidden" name="id" id="create_account_id">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editEtudiant(etudiant) {
            document.getElementById('edit_id').value = etudiant.id;
            document.getElementById('edit_nom').value = etudiant.nom;
            document.getElementById('edit_prenom').value = etudiant.prenom;
            document.getElementById('edit_email').value = etudiant.email;
            document.getElementById('edit_telephone').value = etudiant.telephone || '';
            document.getElementById('edit_date_naissance').value = etudiant.date_naissance || '';
            document.getElementById('edit_numero_etudiant').value = etudiant.numero_etudiant;
            document.getElementById('edit_classe_id').value = etudiant.classe_id;
            document.getElementById('edit_filiere_id').value = etudiant.filiere_id;
            document.getElementById('edit_adresse').value = etudiant.adresse || '';
            
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        function deleteEtudiant(id, nom) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer l'étudiant "${nom}" ?`)) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        function createAccount(id, nom) {
            if (confirm(`Êtes-vous sûr de vouloir créer un compte pour l'étudiant "${nom}" ?\n\nUn mot de passe temporaire sera généré et affiché.`)) {
                document.getElementById('create_account_id').value = id;
                document.getElementById('createAccountForm').submit();
            }
        }

        // Fonction pour copier dans le presse-papiers
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Créer une notification temporaire
                const toast = document.createElement('div');
                toast.className = 'position-fixed top-0 end-0 p-3';
                toast.style.zIndex = '9999';
                toast.innerHTML = `
                    <div class="toast show" role="alert">
                        <div class="toast-header bg-success text-white">
                            <i class="fas fa-check me-2"></i>
                            <strong class="me-auto">Copié !</strong>
                        </div>
                        <div class="toast-body">
                            Texte copié dans le presse-papiers
                        </div>
                    </div>
                `;
                document.body.appendChild(toast);
                
                // Supprimer après 2 secondes
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 2000);
            }).catch(function(err) {
                alert('Erreur lors de la copie : ' + err);
            });
        }

        // Fonction pour imprimer les identifiants
        function printCredentials() {
            <?php if (isset($password_info)): ?>
            const printWindow = window.open('', '_blank');
            const printContent = `
                <html>
                <head>
                    <title>Identifiants de connexion</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                        .info-box { border: 1px solid #ddd; padding: 15px; margin: 10px 0; background: #f9f9f9; }
                        .label { font-weight: bold; color: #666; }
                        .value { font-family: monospace; font-size: 1.2em; background: #fff; padding: 5px; border: 1px solid #ccc; }
                        .instructions { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin-top: 20px; }
                        .footer { text-align: center; margin-top: 30px; font-size: 0.9em; color: #666; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h2>Identifiants de connexion - Système Universitaire</h2>
                        <p>Généré le <?php echo date('d/m/Y à H:i'); ?></p>
                    </div>
                    
                    <div class="info-box">
                        <div class="label">Étudiant :</div>
                        <div class="value"><?php echo htmlspecialchars($password_info['prenom'] . ' ' . $password_info['nom']); ?></div>
                    </div>
                    
                    <div class="info-box">
                        <div class="label">Email de connexion :</div>
                        <div class="value"><?php echo htmlspecialchars($password_info['email']); ?></div>
                    </div>
                    
                    <div class="info-box">
                        <div class="label">Mot de passe temporaire :</div>
                        <div class="value"><?php echo htmlspecialchars($password_info['password']); ?></div>
                    </div>
                    
                    <div class="instructions">
                        <h4>Instructions importantes :</h4>
                        <ul>
                            <li>Connectez-vous sur la page : <strong>student_login.php</strong></li>
                            <li>Utilisez l'email et le mot de passe ci-dessus</li>
                            <li>Vous devrez changer votre mot de passe lors de la première connexion</li>
                            <li>Conservez ces informations en lieu sûr</li>
                        </ul>
                    </div>
                    
                    <div class="footer">
                        <p>Document confidentiel - Ne pas divulguer</p>
                    </div>
                </body>
                </html>
            `;
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.print();
            <?php endif; ?>
        }

        // Auto-hide alerts after 10 seconds (sauf pour les mots de passe)
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert:not([style*="linear-gradient"])');
            alerts.forEach(alert => {
                if (alert.querySelector('.btn-close')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 10000);
    </script>
</body>
</html> 