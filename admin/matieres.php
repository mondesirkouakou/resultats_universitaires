<?php
require_once '../config.php';

// Vérification des permissions - universités et admins peuvent accéder
if (!isLoggedIn() || (!hasPermission('universite') && !hasPermission('admin'))) {
    redirect('../login.php');
}

$pdo = getDatabaseConnection();
$message = '';
$error = '';
// Scoping: détecter si l'utilisateur est une université et récupérer son ID
$isUniversite = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'universite';
$currentUniversiteId = $_SESSION['user_id'] ?? null;
// Déterminer si l'utilisateur est un administrateur
$isAdmin = hasPermission('admin');

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $nom = sanitizeInput($_POST['nom'] ?? '');
        $code = sanitizeInput($_POST['code'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $coefficient = (float)($_POST['coefficient'] ?? 1.0);
        $credits = (int)($_POST['credits'] ?? 0);
        $filiere_id = (int)($_POST['filiere_id'] ?? 0);
        
        if (empty($nom) || empty($code) || $filiere_id <= 0) {
            $error = 'Veuillez remplir tous les champs obligatoires';
        } else {
            try {
                // Vérifier que la filière appartient à l'université courante (si utilisateur université)
                if ($isUniversite) {
                    $check = $pdo->prepare("SELECT 1 FROM universite_filiere WHERE universite_id = ? AND filiere_id = ? LIMIT 1");
                    $check->execute([$currentUniversiteId, $filiere_id]);
                    if (!$check->fetchColumn()) {
                        throw new Exception("Vous n'avez pas l'autorisation d'utiliser cette filière");
                    }
                }

                $stmt = $pdo->prepare("INSERT INTO matieres (nom, code, description, coefficient, credits, filiere_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nom, $code, $description, $coefficient, $credits, $filiere_id]);
                $message = 'Matière créée avec succès';
            } catch (PDOException $e) {
                $error = 'Erreur lors de la création de la matière';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $nom = sanitizeInput($_POST['nom'] ?? '');
        $code = sanitizeInput($_POST['code'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $coefficient = (float)($_POST['coefficient'] ?? 1.0);
        $credits = (int)($_POST['credits'] ?? 0);
        $filiere_id = (int)($_POST['filiere_id'] ?? 0);
        
        if ($id <= 0 || empty($nom) || empty($code) || $filiere_id <= 0) {
            $error = 'Données invalides';
        } else {
            try {
                // Vérifier que la matière appartient à l'université courante et que la nouvelle filière est autorisée
                if ($isUniversite) {
                    // Vérifier possession de la matière
                    $own = $pdo->prepare("SELECT 1
                        FROM matieres m
                        INNER JOIN filieres f ON m.filiere_id = f.id
                        INNER JOIN universite_filiere uf ON f.id = uf.filiere_id
                        WHERE m.id = ? AND uf.universite_id = ? LIMIT 1");
                    $own->execute([$id, $currentUniversiteId]);
                    if (!$own->fetchColumn()) {
                        throw new Exception("Vous n'avez pas l'autorisation de modifier cette matière");
                    }

                    // Vérifier que la nouvelle filière est bien liée à l'université
                    $check = $pdo->prepare("SELECT 1 FROM universite_filiere WHERE universite_id = ? AND filiere_id = ? LIMIT 1");
                    $check->execute([$currentUniversiteId, $filiere_id]);
                    if (!$check->fetchColumn()) {
                        throw new Exception("La filière sélectionnée n'appartient pas à votre université");
                    }
                }

                $stmt = $pdo->prepare("UPDATE matieres SET nom = ?, code = ?, description = ?, coefficient = ?, credits = ?, filiere_id = ? WHERE id = ?");
                $stmt->execute([$nom, $code, $description, $coefficient, $credits, $filiere_id, $id]);
                $message = 'Matière mise à jour avec succès';
            } catch (PDOException $e) {
                $error = 'Erreur lors de la mise à jour de la matière';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id > 0) {
            try {
                // Vérifier que la matière appartient à l'université courante
                if ($isUniversite) {
                    $own = $pdo->prepare("SELECT 1
                        FROM matieres m
                        INNER JOIN filieres f ON m.filiere_id = f.id
                        INNER JOIN universite_filiere uf ON f.id = uf.filiere_id
                        WHERE m.id = ? AND uf.universite_id = ? LIMIT 1");
                    $own->execute([$id, $currentUniversiteId]);
                    if (!$own->fetchColumn()) {
                        throw new Exception("Vous n'avez pas l'autorisation de supprimer cette matière");
                    }
                }

                $stmt = $pdo->prepare("DELETE FROM matieres WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Matière supprimée avec succès';
            } catch (PDOException $e) {
                $error = 'Erreur lors de la suppression de la matière';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// Récupération des matières
$matieres = [];
try {
    if ($isUniversite) {
        $stmt = $pdo->prepare("
            SELECT m.*, f.nom as filiere_nom, GROUP_CONCAT(u.nom SEPARATOR ', ') as universites_noms
            FROM matieres m 
            LEFT JOIN filieres f ON m.filiere_id = f.id 
            LEFT JOIN universite_filiere uf ON f.id = uf.filiere_id
            LEFT JOIN universites u ON uf.universite_id = u.id 
            WHERE uf.universite_id = :uid
            GROUP BY m.id, m.nom, m.description, m.credits, m.filiere_id, m.date_creation, m.statut, f.nom
            ORDER BY m.nom
        ");
        $stmt->execute([':uid' => $currentUniversiteId]);
    } else {
        $stmt = $pdo->query("
            SELECT m.*, f.nom as filiere_nom, GROUP_CONCAT(u.nom SEPARATOR ', ') as universites_noms
            FROM matieres m 
            LEFT JOIN filieres f ON m.filiere_id = f.id 
            LEFT JOIN universite_filiere uf ON f.id = uf.filiere_id
            LEFT JOIN universites u ON uf.universite_id = u.id 
            GROUP BY m.id, m.nom, m.description, m.credits, m.filiere_id, m.date_creation, m.statut, f.nom
            ORDER BY m.nom
        ");
    }
    $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des matières';
}

// Récupération des filières pour le formulaire
$filieres = [];
try {
    if ($isUniversite) {
        $stmt = $pdo->prepare("
            SELECT f.id, f.nom, f.niveau_entree, GROUP_CONCAT(u.nom SEPARATOR ', ') as universites_noms
            FROM filieres f 
            INNER JOIN universite_filiere uf ON f.id = uf.filiere_id AND uf.universite_id = :uid
            LEFT JOIN universites u ON uf.universite_id = u.id 
            GROUP BY f.id, f.nom, f.niveau_entree
            ORDER BY f.nom
        ");
        $stmt->execute([':uid' => $currentUniversiteId]);
    } else {
        $stmt = $pdo->query("
            SELECT f.id, f.nom, f.niveau_entree, GROUP_CONCAT(u.nom SEPARATOR ', ') as universites_noms
            FROM filieres f 
            LEFT JOIN universite_filiere uf ON f.id = uf.filiere_id
            LEFT JOIN universites u ON uf.universite_id = u.id 
            GROUP BY f.id, f.nom, f.niveau_entree
            ORDER BY f.nom
        ");
    }
    $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des filières: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Matières - Interface Université</title>
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
        .coefficient-badge {
            font-size: 0.8em;
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
                        <h2><i class="fas fa-book me-2"></i>Gestion des Matières - Interface Université</h2>
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

            <!-- Formulaire de création -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Nouvelle Matière</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="action" value="create">
                                
                                <div class="col-md-6">
                                    <label for="nom" class="form-label">Nom de la matière *</label>
                                    <input type="text" class="form-control" id="nom" name="nom" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="code" class="form-label">Code de la matière *</label>
                                    <input type="text" class="form-control" id="code" name="code" required>
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
                                
                                <div class="col-md-3">
                                    <label for="coefficient" class="form-label">Coefficient</label>
                                    <input type="number" class="form-control" id="coefficient" name="coefficient" 
                                           value="1.0" step="0.1" min="0.1" max="10">
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="credits" class="form-label">Crédits ECTS</label>
                                    <input type="number" class="form-control" id="credits" name="credits" 
                                           value="0" min="0" max="30">
                                </div>
                                
                                <div class="col-12">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Créer la matière
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste des matières -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Liste des Matières</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Code</th>
                                            <th>Nom</th>
                                            <th>Filière</th>
                                            <th>Coefficient</th>
                                            <th>Crédits</th>
                                            <th>Description</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($matieres as $matiere): ?>
                                            <tr>
                                                <td><?php echo $matiere['id']; ?></td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?php echo htmlspecialchars($matiere['code']); ?>
                                                    </span>
                                                </td>
                                                <td><strong><?php echo htmlspecialchars($matiere['nom']); ?></strong></td>
                                                <td>
                                                    <small>
                                                        <?php echo htmlspecialchars($matiere['filiere_nom'] ?? 'N/A'); ?>
                                                        <br>
                                                        <em class="text-muted">
                                                            <?php echo htmlspecialchars($matiere['universites_noms'] ?? ''); ?>
                                                        </em>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning coefficient-badge">
                                                        <?php echo $matiere['coefficient']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($matiere['credits'] > 0): ?>
                                                        <span class="badge bg-success">
                                                            <?php echo $matiere['credits']; ?> ECTS
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($matiere['description']): ?>
                                                        <?php echo htmlspecialchars(substr($matiere['description'], 0, 50)); ?>
                                                        <?php if (strlen($matiere['description']) > 50): ?>...<?php endif; ?>
                                                    <?php else: ?>
                                                        <em class="text-muted">Aucune description</em>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary btn-action" 
                                                            onclick="editMatiere(<?php echo htmlspecialchars(json_encode($matiere)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger btn-action" 
                                                            onclick="deleteMatiere(<?php echo $matiere['id']; ?>, '<?php echo htmlspecialchars($matiere['nom']); ?>')">
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
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Modifier la Matière</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_nom" class="form-label">Nom de la matière *</label>
                                <input type="text" class="form-control" id="edit_nom" name="nom" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="edit_code" class="form-label">Code de la matière *</label>
                                <input type="text" class="form-control" id="edit_code" name="code" required>
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
                            
                            <div class="col-md-3">
                                <label for="edit_coefficient" class="form-label">Coefficient</label>
                                <input type="number" class="form-control" id="edit_coefficient" name="coefficient" 
                                       step="0.1" min="0.1" max="10">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="edit_credits" class="form-label">Crédits ECTS</label>
                                <input type="number" class="form-control" id="edit_credits" name="credits" 
                                       min="0" max="30">
                            </div>
                            
                            <div class="col-12">
                                <label for="edit_description" class="form-label">Description</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editMatiere(matiere) {
            document.getElementById('edit_id').value = matiere.id;
            document.getElementById('edit_nom').value = matiere.nom;
            document.getElementById('edit_code').value = matiere.code;
            document.getElementById('edit_filiere_id').value = matiere.filiere_id;
            document.getElementById('edit_coefficient').value = matiere.coefficient;
            document.getElementById('edit_credits').value = matiere.credits;
            document.getElementById('edit_description').value = matiere.description || '';
            
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        function deleteMatiere(id, nom) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer la matière "${nom}" ?`)) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html> 