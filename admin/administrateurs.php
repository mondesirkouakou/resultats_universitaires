<?php
require_once '../config.php';

// Vérification des permissions administrateur
if (!isLoggedIn() || $_SESSION['user_type'] !== 'admin') {
    redirect('login.php');
}

$pdo = getDatabaseConnection();
$message = '';
$error = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $nom = sanitizeInput($_POST['nom'] ?? '');
        $prenom = sanitizeInput($_POST['prenom'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $telephone = sanitizeInput($_POST['telephone'] ?? '');
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $niveau_acces = sanitizeInput($_POST['niveau_acces'] ?? 'admin');
        
        if (empty($nom) || empty($prenom) || empty($email) || empty($username) || empty($password)) {
            $error = 'Veuillez remplir tous les champs obligatoires';
        } else {
            try {
                // Vérifier si le nom d'utilisateur existe déjà
                $stmt = $pdo->prepare("SELECT id FROM administrateurs WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = 'Ce nom d\'utilisateur existe déjà';
                } else {
                    // Créer le compte administrateur
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO administrateurs (nom, prenom, email, telephone, username, password, niveau_acces) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nom, $prenom, $email, $telephone, $username, $hashed_password, $niveau_acces]);
                    $message = 'Administrateur créé avec succès';
                }
            } catch (PDOException $e) {
                $error = 'Erreur lors de la création de l\'administrateur';
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $nom = sanitizeInput($_POST['nom'] ?? '');
        $prenom = sanitizeInput($_POST['prenom'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $telephone = sanitizeInput($_POST['telephone'] ?? '');
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $niveau_acces = sanitizeInput($_POST['niveau_acces'] ?? 'admin');
        
        if ($id <= 0 || empty($nom) || empty($prenom) || empty($email) || empty($username)) {
            $error = 'Données invalides';
        } else {
            try {
                // Vérifier si le nom d'utilisateur existe déjà (sauf pour l'admin actuel)
                $stmt = $pdo->prepare("SELECT id FROM administrateurs WHERE username = ? AND id != ?");
                $stmt->execute([$username, $id]);
                if ($stmt->fetch()) {
                    $error = 'Ce nom d\'utilisateur existe déjà';
                } else {
                    if (!empty($password)) {
                        // Mettre à jour avec le nouveau mot de passe
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE administrateurs SET nom = ?, prenom = ?, email = ?, telephone = ?, username = ?, password = ?, niveau_acces = ? WHERE id = ?");
                        $stmt->execute([$nom, $prenom, $email, $telephone, $username, $hashed_password, $niveau_acces, $id]);
                    } else {
                        // Mettre à jour sans changer le mot de passe
                        $stmt = $pdo->prepare("UPDATE administrateurs SET nom = ?, prenom = ?, email = ?, telephone = ?, username = ?, niveau_acces = ? WHERE id = ?");
                        $stmt->execute([$nom, $prenom, $email, $telephone, $username, $niveau_acces, $id]);
                    }
                    $message = 'Administrateur mis à jour avec succès';
                }
            } catch (PDOException $e) {
                $error = 'Erreur lors de la mise à jour de l\'administrateur';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id > 0) {
            // Empêcher la suppression de son propre compte
            if ($id == $_SESSION['user_id']) {
                $error = 'Vous ne pouvez pas supprimer votre propre compte';
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM administrateurs WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'Administrateur supprimé avec succès';
                } catch (PDOException $e) {
                    $error = 'Erreur lors de la suppression de l\'administrateur';
                }
            }
        }
    }
}

// Récupération des administrateurs
$administrateurs = [];
try {
    $stmt = $pdo->query("
        SELECT id, nom, prenom, email, telephone, username, niveau_acces, date_creation 
        FROM administrateurs 
        ORDER BY nom, prenom
    ");
    $administrateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des administrateurs';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Administrateurs - Portail Universitaire</title>
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
        .access-badge {
            font-size: 0.8em;
        }
        .current-user {
            background-color: #e3f2fd !important;
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
                        <h2><i class="fas fa-user-shield me-2"></i>Gestion des Administrateurs</h2>
                        <a href="dashboard.php" class="btn btn-outline-primary">
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
                            <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Nouvel Administrateur</h5>
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
                                    <label for="username" class="form-label">Nom d'utilisateur *</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="password" class="form-label">Mot de passe *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="niveau_acces" class="form-label">Niveau d'accès</label>
                                    <select class="form-select" id="niveau_acces" name="niveau_acces">
                                        <option value="admin">Administrateur</option>
                                        <option value="super_admin">Super Administrateur</option>
                                        <option value="moderateur">Modérateur</option>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Créer l'administrateur
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste des administrateurs -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Liste des Administrateurs</h5>
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
                                            <th>Nom d'utilisateur</th>
                                            <th>Niveau d'accès</th>
                                            <th>Date de création</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($administrateurs as $admin): ?>
                                            <tr class="<?php echo ($admin['id'] == $_SESSION['user_id']) ? 'current-user' : ''; ?>">
                                                <td><?php echo $admin['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($admin['nom'] . ' ' . $admin['prenom']); ?></strong>
                                                    <?php if ($admin['id'] == $_SESSION['user_id']): ?>
                                                        <span class="badge bg-success ms-2">Vous</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="mailto:<?php echo htmlspecialchars($admin['email']); ?>">
                                                        <?php echo htmlspecialchars($admin['email']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php if ($admin['telephone']): ?>
                                                        <a href="tel:<?php echo htmlspecialchars($admin['telephone']); ?>">
                                                            <?php echo htmlspecialchars($admin['telephone']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <code><?php echo htmlspecialchars($admin['username']); ?></code>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $badge_color = $admin['niveau_acces'] === 'super_admin' ? 'danger' : 
                                                                  ($admin['niveau_acces'] === 'admin' ? 'primary' : 'secondary');
                                                    ?>
                                                    <span class="badge bg-<?php echo $badge_color; ?> access-badge">
                                                        <?php echo ucfirst(htmlspecialchars($admin['niveau_acces'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y', strtotime($admin['date_creation'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary btn-action" 
                                                            onclick="editAdmin(<?php echo htmlspecialchars(json_encode($admin)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($admin['id'] != $_SESSION['user_id']): ?>
                                                        <button class="btn btn-sm btn-outline-danger btn-action" 
                                                                onclick="deleteAdmin(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['nom'] . ' ' . $admin['prenom']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
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
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Modifier l'Administrateur</h5>
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
                                <label for="edit_username" class="form-label">Nom d'utilisateur *</label>
                                <input type="text" class="form-control" id="edit_username" name="username" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="edit_password" class="form-label">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
                                <input type="password" class="form-control" id="edit_password" name="password">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="edit_niveau_acces" class="form-label">Niveau d'accès</label>
                                <select class="form-select" id="edit_niveau_acces" name="niveau_acces">
                                    <option value="admin">Administrateur</option>
                                    <option value="super_admin">Super Administrateur</option>
                                    <option value="moderateur">Modérateur</option>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editAdmin(admin) {
            document.getElementById('edit_id').value = admin.id;
            document.getElementById('edit_nom').value = admin.nom;
            document.getElementById('edit_prenom').value = admin.prenom;
            document.getElementById('edit_email').value = admin.email;
            document.getElementById('edit_telephone').value = admin.telephone || '';
            document.getElementById('edit_username').value = admin.username;
            document.getElementById('edit_password').value = '';
            document.getElementById('edit_niveau_acces').value = admin.niveau_acces;
            
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        function deleteAdmin(id, nom) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer l'administrateur "${nom}" ?`)) {
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