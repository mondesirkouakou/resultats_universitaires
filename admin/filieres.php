<?php
require_once dirname(__DIR__) . '/config.php';

// Vérification des permissions - universités et admins peuvent accéder
if (!isLoggedIn() || (!hasPermission('universite') && !hasPermission('admin'))) {
    redirect('../login.php');
}

$pdo = getDatabaseConnection();
$message = '';
$error = '';

// Université courante si utilisateur de type universite
$current_universite_id = hasPermission('universite') ? (int)($_SESSION['user_id'] ?? 0) : 0;
$universite_header = ['nom' => 'Université', 'code' => ''];
if ($current_universite_id > 0) {
    try {
        $stH = $pdo->prepare("SELECT nom, code FROM universites WHERE id = ?");
        $stH->execute([$current_universite_id]);
        $urow = $stH->fetch(PDO::FETCH_ASSOC);
        if ($urow) { $universite_header = $urow; }
    } catch (PDOException $e) { /* ignore header errors */ }
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $nom = sanitizeInput($_POST['nom'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $duree_etudes = (int)($_POST['duree_etudes'] ?? 0);
        $niveau_entree = sanitizeInput($_POST['niveau_entree'] ?? '');
        $universite_ids = $_POST['universite_ids'] ?? [];
        // Si université connectée, forcer l'association à sa propre université
        if ($current_universite_id > 0) {
            $universite_ids = [$current_universite_id];
        }
        
        if (empty($nom) || empty($niveau_entree)) {
            $error = 'Veuillez remplir tous les champs obligatoires';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Insérer la filière
                $stmt = $pdo->prepare("INSERT INTO filieres (nom, description, duree_etudes, niveau_entree) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nom, $description, $duree_etudes, $niveau_entree]);
                $filiere_id = $pdo->lastInsertId();
                
                // Associer les universités
                foreach ($universite_ids as $universite_id) {
                    $universite_id = (int)$universite_id;
                    if ($universite_id > 0) {
                        $stmt = $pdo->prepare("INSERT INTO universite_filiere (universite_id, filiere_id) VALUES (?, ?)");
                        $stmt->execute([$universite_id, $filiere_id]);
                    }
                }
                
                $pdo->commit();
                $message = 'Filière créée avec succès';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Erreur lors de la création de la filière : ' . $e->getMessage();

            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $nom = sanitizeInput($_POST['nom'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $duree_etudes = (int)($_POST['duree_etudes'] ?? 0);
        $niveau_entree = sanitizeInput($_POST['niveau_entree'] ?? '');
        $universite_ids = $_POST['universite_ids'] ?? [];
        if ($current_universite_id > 0) {
            // Vérifier que la filière appartient à cette université
            $chk = $pdo->prepare("SELECT 1 FROM universite_filiere WHERE universite_id = ? AND filiere_id = ?");
            $chk->execute([$current_universite_id, $id]);
            if (!$chk->fetchColumn()) {
                $error = 'Action non autorisée sur une filière hors de votre université';
            }
            // Forcer l'association
            $universite_ids = [$current_universite_id];
        }
        
        if ($id <= 0 || empty($nom) || empty($niveau_entree) || empty($universite_ids) || !empty($error)) {
            $error = 'Données invalides';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Mettre à jour la filière
                $stmt = $pdo->prepare("UPDATE filieres SET nom = ?, description = ?, duree_etudes = ?, niveau_entree = ? WHERE id = ?");
                $stmt->execute([$nom, $description, $duree_etudes, $niveau_entree, $id]);
                
                // Supprimer les anciennes associations
                $stmt = $pdo->prepare("DELETE FROM universite_filiere WHERE filiere_id = ?");
                $stmt->execute([$id]);
                
                // Créer les nouvelles associations
                foreach ($universite_ids as $universite_id) {
                    $universite_id = (int)$universite_id;
                    if ($universite_id > 0) {
                        $stmt = $pdo->prepare("INSERT INTO universite_filiere (universite_id, filiere_id) VALUES (?, ?)");
                        $stmt->execute([$universite_id, $id]);
                    }
                }
                
                $pdo->commit();
                $message = 'Filière mise à jour avec succès';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Erreur lors de la mise à jour de la filière';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id > 0) {
            try {
                // Si université connectée, vérifier la propriété
                if ($current_universite_id > 0) {
                    $chk = $pdo->prepare("SELECT 1 FROM universite_filiere WHERE universite_id = ? AND filiere_id = ?");
                    $chk->execute([$current_universite_id, $id]);
                    if (!$chk->fetchColumn()) {
                        throw new PDOException('Action non autorisée');
                    }
                }

                $pdo->beginTransaction();
                
                // Supprimer les associations
                $stmt = $pdo->prepare("DELETE FROM universite_filiere WHERE filiere_id = ?");
                $stmt->execute([$id]);
                
                // Supprimer la filière
                $stmt = $pdo->prepare("DELETE FROM filieres WHERE id = ?");
                $stmt->execute([$id]);
                
                $pdo->commit();
                $message = 'Filière supprimée avec succès';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Erreur lors de la suppression de la filière';
            }
        }
    }
}

// Récupération des filières
$filieres = [];
try {
    if ($current_universite_id > 0) {
        $stmt = $pdo->prepare("
            SELECT f.*, GROUP_CONCAT(u.nom SEPARATOR ', ') as universites_noms
            FROM filieres f 
            INNER JOIN universite_filiere uf ON f.id = uf.filiere_id AND uf.universite_id = ?
            LEFT JOIN universites u ON uf.universite_id = u.id 
            GROUP BY f.id, f.nom, f.description, f.duree_etudes, f.niveau_entree, f.date_creation, f.statut
            ORDER BY f.nom
        ");
        $stmt->execute([$current_universite_id]);
    } else {
        $stmt = $pdo->query("
            SELECT f.*, GROUP_CONCAT(u.nom SEPARATOR ', ') as universites_noms
            FROM filieres f 
            LEFT JOIN universite_filiere uf ON f.id = uf.filiere_id
            LEFT JOIN universites u ON uf.universite_id = u.id 
            GROUP BY f.id, f.nom, f.description, f.duree_etudes, f.niveau_entree, f.date_creation, f.statut
            ORDER BY f.nom
        ");
    }
    $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des filières: ' . $e->getMessage();
}

// Récupération des universités pour le formulaire
$universites = [];
try {
    if ($current_universite_id > 0) {
        // L'université connectée ne voit que son propre nom si besoin
        $stmt = $pdo->prepare("SELECT id, nom FROM universites WHERE id = ?");
        $stmt->execute([$current_universite_id]);
        $universites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("SELECT id, nom FROM universites ORDER BY nom");
        $universites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des universités';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Filières - Portail des Résultats Universitaires</title>
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
        
        .card {
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border-radius: 15px;
        }
        
        .btn-action {
            margin: 2px;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
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
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Tableau de bord
                    </a>
                    <a class="nav-link active" href="filieres.php">
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
                <div>
                    <h2 class="mb-1">Gestion des Filières</h2>
                    <p class="text-muted mb-0">Créez et gérez les filières d'études de votre université</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="text-end">
                        <div class="fw-bold">Université de Paris</div>
                        <small class="text-muted">UNIV001</small>
                    </div>
                    <div class="avatar">
                        <i class="fas fa-university fa-2x text-primary"></i>
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
                            <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Nouvelle Filière</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="action" value="create">
                                
                                <div class="col-md-6">
                                    <label for="nom" class="form-label">Nom de la filière *</label>
                                    <input type="text" class="form-control" id="nom" name="nom" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="niveau_entree" class="form-label">Niveau d'entrée *</label>
                                    <select class="form-select" id="niveau_entree" name="niveau_entree" required>
                                        <option value="">Sélectionner un niveau</option>
                                        <option value="Bac">Bac</option>
                                        <option value="Bac+1">Bac+1</option>
                                        <option value="Bac+2">Bac+2</option>
                                        <option value="Bac+3">Bac+3</option>
                                        <option value="Bac+4">Bac+4</option>
                                        <option value="Bac+5">Bac+5</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="duree_etudes" class="form-label">Durée d'études (semestres)</label>
                                    <input type="number" class="form-control" id="duree_etudes" name="duree_etudes" min="1" max="12" value="6">
                                </div>
                                
                                
                                
                                <div class="col-12">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Créer la filière
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste des filières -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Liste des Filières</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Nom de la Filière</th>
                                            <th>Niveau d'Entrée</th>
                                            <th>Durée (Semestres)</th>
                                            <th>Universités</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($filieres)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center">Aucune filière trouvée</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($filieres as $filiere): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($filiere['nom']); ?></strong>
                                                        <?php if (!empty($filiere['description'])): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($filiere['description']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?php echo htmlspecialchars($filiere['niveau_entree']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <?php echo $filiere['duree_etudes']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($filiere['universites_noms'])): ?>
                                                            <small class="text-muted">
                                                                <i class="fas fa-university me-1"></i>
                                                                <?php echo htmlspecialchars($filiere['universites_noms']); ?>
                                                            </small>
                                                        <?php else: ?>
                                                            <small class="text-muted">Aucune université associée</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $filiere['statut'] === 'actif' ? 'success' : 'danger'; ?>">
                                                            <?php echo $filiere['statut'] ? ucfirst($filiere['statut']) : 'Inconnu'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex gap-2">
                                                            <button class="btn btn-sm btn-outline-primary"
                                                                    onclick="editFiliere(<?php echo $filiere['id']; ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger"
                                                                    onclick="deleteFiliere(<?php echo $filiere['id']; ?>, '<?php echo addslashes($filiere['nom']); ?>')">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
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
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier la Filière</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="mb-3">
                                <label for="edit_nom" class="form-label">Nom de la filière *</label>
                                <input type="text" class="form-control" id="edit_nom" name="nom" required>
                            </div>
                            
                        <div class="mb-3">
                            <label for="edit_niveau_entree" class="form-label">Niveau d'entrée *</label>
                            <select class="form-select" id="edit_niveau_entree" name="niveau_entree" required>
                                <option value="Bac">Bac</option>
                                <option value="Bac+1">Bac+1</option>
                                <option value="Bac+2">Bac+2</option>
                                <option value="Bac+3">Bac+3</option>
                                <option value="Bac+4">Bac+4</option>
                                <option value="Bac+5">Bac+5</option>
                                </select>
                            </div>
                            
                        <div class="mb-3">
                            <label for="edit_duree_etudes" class="form-label">Durée d'études (semestres)</label>
                            <input type="number" class="form-control" id="edit_duree_etudes" name="duree_etudes" min="1" max="12" required>
                        </div>
                        
                        
                            
                        <div class="mb-3">
                                <label for="edit_description" class="form-label">Description</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
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

    <!-- Modal de suppression -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer cette filière ?</p>
                    <p class="text-danger"><small>Cette action est irréversible.</small></p>
                </div>
                <form method="POST" id="deleteForm">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">Supprimer</button>
                    </div>
    </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Données des filières pour l'édition
        const filieresData = <?php echo json_encode($filieres); ?>;
        
        function editFiliere(id) {
            const filiere = filieresData.find(f => f.id == id);
            if (filiere) {
            document.getElementById('edit_id').value = filiere.id;
            document.getElementById('edit_nom').value = filiere.nom;
                document.getElementById('edit_niveau_entree').value = filiere.niveau_entree || 'Bac';
                document.getElementById('edit_duree_etudes').value = filiere.duree_etudes || 6;
            document.getElementById('edit_description').value = filiere.description || '';
            
                // Clear previous selections
                const universiteSelect = document.getElementById('edit_universite_ids');
                for (let option of universiteSelect.options) {
                    option.selected = false;
                }
                
                // Note: For now, we'll need to fetch the associated universities via AJAX
                // or store them in the filieresData. For simplicity, we'll leave it empty
                // and the user can reselect the universities
                
                new bootstrap.Modal(document.getElementById('editModal')).show();
            }
        }
        
        function deleteFiliere(id, nom) {
            document.getElementById('delete_id').value = id;
            // Optionnel: afficher le nom de la filière dans le modal
            const modalBody = document.querySelector('#deleteModal .modal-body p');
            if (modalBody && nom) {
                modalBody.innerHTML = `Êtes-vous sûr de vouloir supprimer la filière "<strong>${nom}</strong>" ?`;
            }
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
        
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