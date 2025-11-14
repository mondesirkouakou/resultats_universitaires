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
        $matiere_ids = isset($_POST['matiere_ids']) && is_array($_POST['matiere_ids'])
            ? array_filter(array_map('intval', $_POST['matiere_ids']))
            : [];
        $grade = sanitizeInput($_POST['grade'] ?? '');
        
        if (empty($nom) || empty($prenom) || empty($email)) {
            $error = 'Veuillez remplir tous les champs obligatoires';
        } else {
            try {
                if ($isUniversite && !$isAdmin && !empty($matiere_ids)) {
                    // Vérifier que toutes les matières appartiennent à la même université
                    $inPlaceholders = implode(',', array_fill(0, count($matiere_ids), '?'));
                    $params = $matiere_ids;
                    $params[] = $currentUniversiteId;
                    $chk = $pdo->prepare("SELECT COUNT(DISTINCT m.id) FROM matieres m JOIN filieres f ON m.filiere_id = f.id JOIN universite_filiere uf ON uf.filiere_id = f.id WHERE m.id IN ($inPlaceholders) AND uf.universite_id = ?");
                    $chk->execute($params);
                    if ((int)$chk->fetchColumn() !== count($matiere_ids)) {
                        throw new Exception("Action non autorisée: matière(s) hors de votre université");
                    }
                }
                // Insérer le professeur (on ne renseigne plus matiere_id direct, on passe NULL)
                $stmt = $pdo->prepare("INSERT INTO professeurs (nom, prenom, email, telephone, matiere_id, grade) VALUES (?, ?, ?, ?, NULL, ?)");
                $stmt->execute([$nom, $prenom, $email, $telephone, $grade]);
                $newProfId = (int)$pdo->lastInsertId();

                // Gérer les matières multiples via la table de jonction
                if (!empty($matiere_ids)) {
                    $ins = $pdo->prepare("INSERT INTO matiere_professeur (matiere_id, professeur_id) VALUES (?, ?)");
                    foreach ($matiere_ids as $mid) {
                        $ins->execute([$mid, $newProfId]);
                    }
                }
                $message = 'Professeur créé avec succès';
            } catch (PDOException $e) {
                $error = 'Erreur lors de la création du professeur';
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
        $matiere_ids = isset($_POST['matiere_ids']) && is_array($_POST['matiere_ids'])
            ? array_filter(array_map('intval', $_POST['matiere_ids']))
            : [];
        $grade = sanitizeInput($_POST['grade'] ?? '');
        
        if ($id <= 0 || empty($nom) || empty($prenom) || empty($email)) {
            $error = 'Données invalides';
        } else {
            try {
                if ($isUniversite && !$isAdmin) {
                    // Vérifier que le professeur appartient à l'université (au moins une matière liée côté université)
                    $chkProf = $pdo->prepare("SELECT 1 FROM professeurs p JOIN matiere_professeur mp ON mp.professeur_id = p.id JOIN matieres m ON m.id = mp.matiere_id JOIN filieres f ON f.id = m.filiere_id JOIN universite_filiere uf ON uf.filiere_id = f.id WHERE p.id = ? AND uf.universite_id = ? LIMIT 1");
                    $chkProf->execute([$id, $currentUniversiteId]);
                    if (!$chkProf->fetch()) {
                        throw new Exception("Action non autorisée: professeur hors de votre université");
                    }
                    if (!empty($matiere_ids)) {
                        $inPlaceholders = implode(',', array_fill(0, count($matiere_ids), '?'));
                        $params = $matiere_ids;
                        $params[] = $currentUniversiteId;
                        $chk = $pdo->prepare("SELECT COUNT(DISTINCT m.id) FROM matieres m JOIN filieres f ON m.filiere_id = f.id JOIN universite_filiere uf ON uf.filiere_id = f.id WHERE m.id IN ($inPlaceholders) AND uf.universite_id = ?");
                        $chk->execute($params);
                        if ((int)$chk->fetchColumn() !== count($matiere_ids)) {
                            throw new Exception("Action non autorisée: matière(s) hors de votre université");
                        }
                    }
                }
                // Mettre à jour les infos principales (on met matiere_id à NULL, on s'appuie sur la jonction)
                $stmt = $pdo->prepare("UPDATE professeurs SET nom = ?, prenom = ?, email = ?, telephone = ?, matiere_id = NULL, grade = ? WHERE id = ?");
                $stmt->execute([$nom, $prenom, $email, $telephone, $grade, $id]);

                // Remplacer les liaisons matières
                $pdo->prepare("DELETE FROM matiere_professeur WHERE professeur_id = ?")->execute([$id]);
                if (!empty($matiere_ids)) {
                    $ins = $pdo->prepare("INSERT INTO matiere_professeur (matiere_id, professeur_id) VALUES (?, ?)");
                    foreach ($matiere_ids as $mid) {
                        $ins->execute([$mid, $id]);
                    }
                }
                $message = 'Professeur mis à jour avec succès';
            } catch (PDOException $e) {
                $error = 'Erreur lors de la mise à jour du professeur';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id > 0) {
            try {
                if ($isUniversite && !$isAdmin) {
                    $chkProf = $pdo->prepare("SELECT 1 FROM professeurs p JOIN matiere_professeur mp ON mp.professeur_id = p.id JOIN matieres m ON m.id = mp.matiere_id JOIN filieres f ON f.id = m.filiere_id JOIN universite_filiere uf ON uf.filiere_id = f.id WHERE p.id = ? AND uf.universite_id = ? LIMIT 1");
                    $chkProf->execute([$id, $currentUniversiteId]);
                    if (!$chkProf->fetch()) {
                        throw new Exception("Action non autorisée: professeur hors de votre université");
                    }
                }
                // Supprimer d'abord les liaisons matières
                $pdo->prepare("DELETE FROM matiere_professeur WHERE professeur_id = ?")->execute([$id]);
                // Puis supprimer le professeur
                $stmt = $pdo->prepare("DELETE FROM professeurs WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Professeur supprimé avec succès';
            } catch (PDOException $e) {
                $error = 'Erreur lors de la suppression du professeur';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'create_account') {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id > 0) {
            if ($isUniversite && !$isAdmin) {
                $chkProf = $pdo->prepare("SELECT 1 FROM professeurs p JOIN matiere_professeur mp ON mp.professeur_id = p.id JOIN matieres m ON m.id = mp.matiere_id JOIN filieres f ON f.id = m.filiere_id JOIN universite_filiere uf ON uf.filiere_id = f.id WHERE p.id = ? AND uf.universite_id = ? LIMIT 1");
                $chkProf->execute([$id, $currentUniversiteId]);
                if (!$chkProf->fetch()) {
                    $error = "Action non autorisée: professeur hors de votre université";
                }
            }
            if (!$error) {
                $result = createProfessorAccount($pdo, $id);
                if ($result['success']) {
                    $message = "COMPTE_CREE";
                    $password_info = [
                        'nom' => $result['nom'],
                        'prenom' => $result['prenom'],
                        'email' => $result['email'],
                        'password' => $result['password'],
                        'type' => 'professeur'
                    ];
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}

// Récupération des professeurs
$professeurs = [];
try {
    if ($isUniversite && !$isAdmin) {
        // Lister uniquement les professeurs liés à au moins une matière de l'université
        $stmt = $pdo->prepare("
            SELECT p.*,
                   CASE WHEN p.mot_de_passe IS NOT NULL THEN 1 ELSE 0 END as has_account,
                   mm.matieres_noms,
                   mm.matieres_codes,
                   mm.matiere_ids_csv
            FROM professeurs p
            JOIN matiere_professeur mp ON mp.professeur_id = p.id
            JOIN matieres m ON m.id = mp.matiere_id
            JOIN filieres f ON f.id = m.filiere_id
            JOIN universite_filiere uf ON uf.filiere_id = f.id
            LEFT JOIN (
                SELECT mp2.professeur_id,
                       GROUP_CONCAT(m2.nom ORDER BY m2.nom SEPARATOR ', ') AS matieres_noms,
                       GROUP_CONCAT(m2.code ORDER BY m2.nom SEPARATOR ', ') AS matieres_codes,
                       GROUP_CONCAT(m2.id ORDER BY m2.nom SEPARATOR ',') AS matiere_ids_csv
                FROM matiere_professeur mp2
                JOIN matieres m2 ON m2.id = mp2.matiere_id
                GROUP BY mp2.professeur_id
            ) mm ON mm.professeur_id = p.id
            WHERE uf.universite_id = ?
            GROUP BY p.id
            ORDER BY p.nom, p.prenom
        ");
        $stmt->execute([$currentUniversiteId]);
        $professeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("
            SELECT p.*,
                   CASE WHEN p.mot_de_passe IS NOT NULL THEN 1 ELSE 0 END as has_account,
                   mm.matieres_noms,
                   mm.matieres_codes,
                   mm.matiere_ids_csv
            FROM professeurs p
            LEFT JOIN (
                SELECT mp.professeur_id,
                       GROUP_CONCAT(m.nom ORDER BY m.nom SEPARATOR ', ') AS matieres_noms,
                       GROUP_CONCAT(m.code ORDER BY m.nom SEPARATOR ', ') AS matieres_codes,
                       GROUP_CONCAT(m.id ORDER BY m.nom SEPARATOR ',') AS matiere_ids_csv
                FROM matiere_professeur mp
                JOIN matieres m ON m.id = mp.matiere_id
                GROUP BY mp.professeur_id
            ) mm ON mm.professeur_id = p.id
            ORDER BY p.nom, p.prenom
        ");
        $professeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des professeurs';
}

// Récupération des matières pour le formulaire
$matieres = [];
try {
    if ($isUniversite && !$isAdmin) {
        $stmt = $pdo->prepare("
            SELECT m.id, m.nom, m.code, f.nom as filiere_nom 
            FROM matieres m 
            JOIN filieres f ON m.filiere_id = f.id 
            JOIN universite_filiere uf ON uf.filiere_id = f.id
            WHERE m.statut = 'actif' AND uf.universite_id = ?
            ORDER BY f.nom, m.nom
        ");
        $stmt->execute([$currentUniversiteId]);
        $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("
            SELECT m.id, m.nom, m.code, f.nom as filiere_nom 
            FROM matieres m 
            LEFT JOIN filieres f ON m.filiere_id = f.id 
            WHERE m.statut = 'actif'
            ORDER BY f.nom, m.nom
        ");
        $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des matières';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Professeurs - Interface Université</title>
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
        .grade-badge {
            font-size: 0.8em;
        }
        .specialite-badge {
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
                        <h2><i class="fas fa-chalkboard-teacher me-2"></i>Gestion des Professeurs - Interface Université</h2>
                        <a href="<?php echo $isAdmin ? 'dashboard.php' : 'universite_dashboard.php'; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Retour au Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if (isset($password_info)): ?>
                <div class="alert alert-success border-0 shadow-lg" role="alert" style="background: linear-gradient(135deg, #6f42c1, #e83e8c);">
                    <div class="d-flex align-items-center mb-3">
                        <i class="fas fa-chalkboard-teacher fa-2x text-white me-3"></i>
                        <div>
                            <h5 class="text-white mb-0">✅ Compte professeur créé avec succès !</h5>
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
                                • Communiquez ces informations au professeur de manière sécurisée<br>
                                • Le professeur devra changer son mot de passe lors de sa première connexion<br>
                                • Page de connexion : <code class="bg-white text-dark px-1 rounded">student_login.php</code>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-light" onclick="printProfessorCredentials()">
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
                            <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Nouveau Professeur</h5>
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
                                    <label for="matiere_ids" class="form-label">Matières (plusieurs choix)</label>
                                    <select class="form-select" id="matiere_ids" name="matiere_ids[]" multiple size="6">
                                        <?php foreach ($matieres as $matiere): ?>
                                            <option value="<?php echo $matiere['id']; ?>">
                                                <?php echo htmlspecialchars($matiere['nom']); ?> 
                                                (<?php echo htmlspecialchars($matiere['code']); ?>) - 
                                                <?php echo htmlspecialchars($matiere['filiere_nom'] ?? 'N/A'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Maintenez Ctrl (ou Cmd sur Mac) pour sélectionner plusieurs matières.</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="grade" class="form-label">Grade</label>
                                    <select class="form-select" id="grade" name="grade">
                                        <option value="">Sélectionner un grade</option>
                                        <option value="Professeur">Professeur</option>
                                        <option value="Maître de Conférences">Maître de Conférences</option>
                                        <option value="Chargé de Cours">Chargé de Cours</option>
                                        <option value="Assistant">Assistant</option>
                                        <option value="Docteur">Docteur</option>
                                        <option value="Chercheur">Chercheur</option>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Créer le professeur
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste des professeurs -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Liste des Professeurs</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom</th>
                                            <th>Email</th>
                                            <th>Téléphone</th>
                                            <th>Matières</th>
                                            <th>Grade</th>
                                            <th>Compte</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($professeurs as $professeur): ?>
                                            <tr>
                                                <td><?php echo $professeur['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($professeur['nom'] . ' ' . $professeur['prenom']); ?></strong>
                                                </td>
                                                <td>
                                                    <a href="mailto:<?php echo htmlspecialchars($professeur['email']); ?>">
                                                        <?php echo htmlspecialchars($professeur['email']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php if ($professeur['telephone']): ?>
                                                        <a href="tel:<?php echo htmlspecialchars($professeur['telephone']); ?>">
                                                            <?php echo htmlspecialchars($professeur['telephone']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($professeur['matieres_noms'])): ?>
                                                        <?php 
                                                            $noms = explode(', ', $professeur['matieres_noms']);
                                                            $codes = !empty($professeur['matieres_codes']) ? explode(', ', $professeur['matieres_codes']) : [];
                                                            foreach ($noms as $idx => $nomMat) {
                                                                $code = $codes[$idx] ?? '';
                                                        ?>
                                                            <span class="badge bg-info matiere-badge me-1 mb-1">
                                                                <?php echo htmlspecialchars($nomMat); ?>
                                                                <?php if (!empty($code)): ?> (<?php echo htmlspecialchars($code); ?>)<?php endif; ?>
                                                            </span>
                                                        <?php } ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Aucune matière</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($professeur['grade']): ?>
                                                        <span class="badge bg-warning grade-badge">
                                                            <?php echo htmlspecialchars($professeur['grade']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($professeur['has_account']): ?>
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
                                                            onclick="editProfesseur(<?php echo htmlspecialchars(json_encode($professeur)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if (!$professeur['has_account'] && !empty($professeur['email'])): ?>
                                                        <button class="btn btn-sm btn-outline-success btn-action" 
                                                                onclick="createProfessorAccount(<?php echo $professeur['id']; ?>, '<?php echo htmlspecialchars($professeur['nom'] . ' ' . $professeur['prenom']); ?>')" 
                                                                title="Créer un compte">
                                                            <i class="fas fa-user-plus"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-outline-danger btn-action" 
                                                            onclick="deleteProfesseur(<?php echo $professeur['id']; ?>, '<?php echo htmlspecialchars($professeur['nom'] . ' ' . $professeur['prenom']); ?>')">
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
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Modifier le Professeur</h5>
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
                                <label for="edit_matiere_ids" class="form-label">Matières (plusieurs choix)</label>
                                <select class="form-select" id="edit_matiere_ids" name="matiere_ids[]" multiple size="6">
                                    <?php foreach ($matieres as $matiere): ?>
                                        <option value="<?php echo $matiere['id']; ?>">
                                            <?php echo htmlspecialchars($matiere['nom']); ?> 
                                            (<?php echo htmlspecialchars($matiere['code']); ?>) - 
                                            <?php echo htmlspecialchars($matiere['filiere_nom'] ?? 'N/A'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Maintenez Ctrl (ou Cmd sur Mac) pour sélectionner plusieurs matières.</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="edit_grade" class="form-label">Grade</label>
                                <select class="form-select" id="edit_grade" name="grade">
                                    <option value="">Sélectionner un grade</option>
                                    <option value="Professeur">Professeur</option>
                                    <option value="Maître de Conférences">Maître de Conférences</option>
                                    <option value="Chargé de Cours">Chargé de Cours</option>
                                    <option value="Assistant">Assistant</option>
                                    <option value="Docteur">Docteur</option>
                                    <option value="Chercheur">Chercheur</option>
                                </select>
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
        function editProfesseur(professeur) {
            document.getElementById('edit_id').value = professeur.id;
            document.getElementById('edit_nom').value = professeur.nom;
            document.getElementById('edit_prenom').value = professeur.prenom;
            document.getElementById('edit_email').value = professeur.email;
            document.getElementById('edit_telephone').value = professeur.telephone || '';
            document.getElementById('edit_grade').value = professeur.grade || '';

            // Pré-sélection des matières multiples
            const select = document.getElementById('edit_matiere_ids');
            // Réinitialiser
            for (const opt of select.options) { opt.selected = false; }
            if (professeur.matiere_ids_csv) {
                const ids = String(professeur.matiere_ids_csv).split(',').map(x => x.trim());
                for (const opt of select.options) {
                    if (ids.includes(String(opt.value))) opt.selected = true;
                }
            }

            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        function deleteProfesseur(id, nom) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer le professeur "${nom}" ?`)) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        function createProfessorAccount(id, nom) {
            if (confirm(`Êtes-vous sûr de vouloir créer un compte pour le professeur "${nom}" ?\n\nUn mot de passe temporaire sera généré et affiché.`)) {
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

        // Fonction pour imprimer les identifiants professeur
        function printProfessorCredentials() {
            <?php if (isset($password_info)): ?>
            const printWindow = window.open('', '_blank');
            const printContent = `
                <html>
                <head>
                    <title>Identifiants de connexion - Professeur</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        .header { text-align: center; border-bottom: 2px solid #6f42c1; padding-bottom: 10px; margin-bottom: 20px; }
                        .info-box { border: 1px solid #ddd; padding: 15px; margin: 10px 0; background: #f9f9f9; }
                        .label { font-weight: bold; color: #666; }
                        .value { font-family: monospace; font-size: 1.2em; background: #fff; padding: 5px; border: 1px solid #ccc; }
                        .instructions { background: #f3e5f5; border-left: 4px solid #6f42c1; padding: 15px; margin-top: 20px; }
                        .footer { text-align: center; margin-top: 30px; font-size: 0.9em; color: #666; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h2>Identifiants de connexion - Professeur</h2>
                        <h3>Système Universitaire</h3>
                        <p>Généré le <?php echo date('d/m/Y à H:i'); ?></p>
                    </div>
                    
                    <div class="info-box">
                        <div class="label">Professeur :</div>
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
                            <li>Accès au dashboard professeur après connexion</li>
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