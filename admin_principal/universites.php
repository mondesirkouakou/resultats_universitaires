<?php
require_once '../config.php';

// Vérification de la session - seul l'admin principal peut accéder
if (!isLoggedIn() || $_SESSION['user_type'] !== 'admin_principal') {
    redirect('../login.php');
}

$pdo = getDatabaseConnection();
$message = '';
$error = '';

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $nom = sanitizeInput($_POST['nom']);
                $adresse = sanitizeInput($_POST['adresse']);
                $telephone = sanitizeInput($_POST['telephone']);
                $email = sanitizeInput($_POST['email']);
                $site_web = sanitizeInput($_POST['site_web']);
                
                if (empty($nom)) {
                    $error = "Le nom de l'université est requis.";
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO universites (nom, adresse, telephone, email, site_web) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$nom, $adresse, $telephone, $email, $site_web]);
                        $message = "Université créée avec succès !";
                    } catch (PDOException $e) {
                        $error = "Erreur lors de la création de l'université.";
                    }
                }
                break;
                
            case 'update':
                $id = (int)$_POST['id'];
                $nom = sanitizeInput($_POST['nom']);
                $adresse = sanitizeInput($_POST['adresse']);
                $telephone = sanitizeInput($_POST['telephone']);
                $email = sanitizeInput($_POST['email']);
                $site_web = sanitizeInput($_POST['site_web']);
                
                if (empty($nom)) {
                    $error = "Le nom de l'université est requis.";
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE universites SET nom = ?, adresse = ?, telephone = ?, email = ?, site_web = ? WHERE id = ?");
                        $stmt->execute([$nom, $adresse, $telephone, $email, $site_web, $id]);
                        $message = "Université mise à jour avec succès !";
                    } catch (PDOException $e) {
                        $error = "Erreur lors de la mise à jour de l'université.";
                    }
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                try {
                    $stmt = $pdo->prepare("DELETE FROM universites WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = "Université supprimée avec succès !";
                } catch (PDOException $e) {
                    $error = "Erreur lors de la suppression de l'université.";
                }
                break;
        }
    }
}

// Récupération des universités
try {
    $stmt = $pdo->query("SELECT * FROM universites ORDER BY nom");
    $universites = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des universités.";
    $universites = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Universités - Admin Principal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-container {
            min-height: 100vh;
            background: #f8f9fa;
            padding: 20px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
        }
        .btn-action {
            margin: 2px;
        }
        .table th {
            background: #f8f9fa;
            border-top: none;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-university text-primary"></i> Gestion des Universités</h2>
                <p class="text-muted">Administration principale - Gestion des universités</p>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Retour au Dashboard
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Formulaire de création -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-plus"></i> Créer une nouvelle université</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="nom" class="form-label">Nom de l'université *</label>
                                <input type="text" class="form-control" id="nom" name="nom" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="telephone" class="form-label">Téléphone</label>
                                <input type="text" class="form-control" id="telephone" name="telephone">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="site_web" class="form-label">Site web</label>
                                <input type="url" class="form-control" id="site_web" name="site_web">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="adresse" class="form-label">Adresse</label>
                        <textarea class="form-control" id="adresse" name="adresse" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Créer l'université
                    </button>
                </form>
            </div>
        </div>

        <!-- Liste des universités -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-list"></i> Liste des universités</h5>
            </div>
            <div class="card-body">
                <?php if (empty($universites)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-university fa-3x mb-3"></i>
                        <p>Aucune université enregistrée.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Site web</th>
                                    <th>Date création</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($universites as $universite): ?>
                                    <tr>
                                        <td><?php echo $universite['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($universite['nom']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($universite['email'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($universite['telephone'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($universite['site_web']): ?>
                                                <a href="<?php echo htmlspecialchars($universite['site_web']); ?>" target="_blank">
                                                    <i class="fas fa-external-link-alt"></i> Visiter
                                                </a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($universite['date_creation'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary btn-action" 
                                                    onclick="editUniversite(<?php echo htmlspecialchars(json_encode($universite)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger btn-action" 
                                                    onclick="deleteUniversite(<?php echo $universite['id']; ?>, '<?php echo htmlspecialchars($universite['nom']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal d'édition -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier l'université</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_nom" class="form-label">Nom de l'université *</label>
                                    <input type="text" class="form-control" id="edit_nom" name="nom" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="edit_email" name="email">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_telephone" class="form-label">Téléphone</label>
                                    <input type="text" class="form-control" id="edit_telephone" name="telephone">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_site_web" class="form-label">Site web</label>
                                    <input type="url" class="form-control" id="edit_site_web" name="site_web">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_adresse" class="form-label">Adresse</label>
                            <textarea class="form-control" id="edit_adresse" name="adresse" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Mettre à jour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formulaire de suppression -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUniversite(universite) {
            document.getElementById('edit_id').value = universite.id;
            document.getElementById('edit_nom').value = universite.nom;
            document.getElementById('edit_email').value = universite.email || '';
            document.getElementById('edit_telephone').value = universite.telephone || '';
            document.getElementById('edit_site_web').value = universite.site_web || '';
            document.getElementById('edit_adresse').value = universite.adresse || '';
            
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        function deleteUniversite(id, nom) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer l'université "${nom}" ?`)) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        // Auto-hide alerts
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