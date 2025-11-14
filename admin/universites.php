<?php
require_once '../config.php';
require_once '../includes/user_accounts.php';

// Vérification des permissions administrateur
if (!isLoggedIn() || !hasPermission('admin')) {
    redirect('../login.php');
}

$pdo = getDatabaseConnection();
$message = '';
$error = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $nom = sanitizeInput($_POST['nom'] ?? '');
        $code = sanitizeInput($_POST['code'] ?? '');
        $adresse = sanitizeInput($_POST['adresse'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $telephone = sanitizeInput($_POST['telephone'] ?? '');
        $site_web = sanitizeInput($_POST['site_web'] ?? '');
        $slogan = sanitizeInput($_POST['slogan'] ?? '');
        
        if (empty($nom) || empty($code)) {
            $error = 'Veuillez remplir tous les champs obligatoires';
        } else {
            try {
                // Vérifier si le code existe déjà
                $stmt = $pdo->prepare("SELECT id FROM universites WHERE code = ?");
                $stmt->execute([$code]);
                if ($stmt->fetch()) {
                    $error = 'Ce code d\'université existe déjà';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO universites (nom, code, adresse, email, telephone, site_web, slogan, date_creation) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$nom, $code, $adresse, $email, $telephone, $site_web, $slogan]);
                    $newId = (int)$pdo->lastInsertId();

                    // Upload du logo si fourni
                    if (!empty($_FILES['logo']['name']) && $newId > 0) {
                        $uploadOk = false;
                        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/svg+xml' => 'svg', 'image/webp' => 'webp'];
                        $mime = mime_content_type($_FILES['logo']['tmp_name']);
                        if (isset($allowed[$mime])) {
                            $ext = $allowed[$mime];
                            $baseDirFs = realpath(__DIR__ . '/../assets');
                            if ($baseDirFs === false) { $baseDirFs = __DIR__ . '/../assets'; }
                            $targetDirFs = $baseDirFs . '/uploads/universites/' . $newId;
                            if (!is_dir($targetDirFs)) { @mkdir($targetDirFs, 0777, true); }
                            $targetName = 'logo.' . $ext;
                            $targetPathFs = $targetDirFs . '/' . $targetName;
                            if (@move_uploaded_file($_FILES['logo']['tmp_name'], $targetPathFs)) {
                                // Chemin web relatif depuis la racine Web
                                $logoPathWeb = 'assets/uploads/universites/' . $newId . '/' . $targetName;
                                $upd = $pdo->prepare("UPDATE universites SET logo_path = ? WHERE id = ?");
                                $upd->execute([$logoPathWeb, $newId]);
                                $uploadOk = true;
                            }
                        }
                    }

                    $message = 'Université créée avec succès';
                }
            } catch (PDOException $e) {
                $error = 'Erreur lors de la création de l\'université';
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $nom = sanitizeInput($_POST['nom'] ?? '');
        $code = sanitizeInput($_POST['code'] ?? '');
        $adresse = sanitizeInput($_POST['adresse'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $telephone = sanitizeInput($_POST['telephone'] ?? '');
        $site_web = sanitizeInput($_POST['site_web'] ?? '');
        $slogan = sanitizeInput($_POST['slogan'] ?? '');
        
        if ($id <= 0 || empty($nom) || empty($code)) {
            $error = 'Données invalides';
        } else {
            try {
                // Vérifier si le code existe déjà (sauf pour l'université actuelle)
                $stmt = $pdo->prepare("SELECT id FROM universites WHERE code = ? AND id != ?");
                $stmt->execute([$code, $id]);
                if ($stmt->fetch()) {
                    $error = 'Ce code d\'université existe déjà';
                } else {
                    $stmt = $pdo->prepare("UPDATE universites SET nom = ?, code = ?, adresse = ?, email = ?, telephone = ?, site_web = ?, slogan = ? WHERE id = ?");
                    $stmt->execute([$nom, $code, $adresse, $email, $telephone, $site_web, $slogan, $id]);

                    // Upload du logo si fourni
                    if (!empty($_FILES['logo']['name']) && $id > 0) {
                        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/svg+xml' => 'svg', 'image/webp' => 'webp'];
                        $mime = mime_content_type($_FILES['logo']['tmp_name']);
                        if (isset($allowed[$mime])) {
                            $ext = $allowed[$mime];
                            $baseDirFs = realpath(__DIR__ . '/../assets');
                            if ($baseDirFs === false) { $baseDirFs = __DIR__ . '/../assets'; }
                            $targetDirFs = $baseDirFs . '/uploads/universites/' . $id;
                            if (!is_dir($targetDirFs)) { @mkdir($targetDirFs, 0777, true); }
                            // Supprime anciens logos connus
                            foreach (['png','jpg','jpeg','svg','webp'] as $e) {
                                $f = $targetDirFs . '/logo.' . $e;
                                if (is_file($f)) { @unlink($f); }
                            }
                            $targetName = 'logo.' . $ext;
                            $targetPathFs = $targetDirFs . '/' . $targetName;
                            if (@move_uploaded_file($_FILES['logo']['tmp_name'], $targetPathFs)) {
                                $logoPathWeb = 'assets/uploads/universites/' . $id . '/' . $targetName;
                                $upd = $pdo->prepare("UPDATE universites SET logo_path = ? WHERE id = ?");
                                $upd->execute([$logoPathWeb, $id]);
                            }
                        }
                    }

                    $message = 'Université mise à jour avec succès';
                }
            } catch (PDOException $e) {
                $error = 'Erreur lors de la mise à jour de l\'université';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id > 0) {
            try {
                $pdo->beginTransaction();
                
                // Supprimer les associations
                $stmt = $pdo->prepare("DELETE FROM universite_filiere WHERE universite_id = ?");
                $stmt->execute([$id]);
                
                // Supprimer l'université
                $stmt = $pdo->prepare("DELETE FROM universites WHERE id = ?");
                $stmt->execute([$id]);
                
                $pdo->commit();
                $message = 'Université supprimée avec succès';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Erreur lors de la suppression de l\'université';
            }
        }
    } elseif ($action === 'create_universite_account') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $res = createUniversityAccount($pdo, $id);
                if (!empty($res['success'])) {
                    $message = "Compte université créé. Email: " . htmlspecialchars($res['email']) . " — Mot de passe temporaire: " . htmlspecialchars($res['password']);
                } else {
                    $error = $res['message'] ?? "Erreur lors de la création du compte";
                }
            } catch (PDOException $e) {
                $error = "Erreur lors de la création du compte";
            }
        }
    }
}

// Récupération des universités
$universites = [];
try {
    $stmt = $pdo->query("
        SELECT u.*, 
               COUNT(DISTINCT uf.filiere_id) as nb_filieres,
               COUNT(DISTINCT e.id) as nb_etudiants,
               u.email,
               u.mot_de_passe
        FROM universites u 
        LEFT JOIN universite_filiere uf ON u.id = uf.universite_id 
        LEFT JOIN filieres f ON uf.filiere_id = f.id 
        LEFT JOIN etudiants e ON f.id = e.filiere_id 
        GROUP BY u.id 
        ORDER BY u.nom
    ");
    $universites = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des universités';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Universités - Portail des Résultats Universitaires</title>
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
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="p-4">
                <h4 class="text-primary mb-4">
                    <i class="fas fa-graduation-cap"></i>
                    Admin Principal
                </h4>
                
                <nav class="nav flex-column">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        Tableau de bord
                    </a>
                    <a href="universites.php" class="nav-link active">
                        <i class="fas fa-university"></i>
                        Gérer les universités
                    </a>
                    <a href="administrateurs.php" class="nav-link">
                        <i class="fas fa-users-cog"></i>
                        Gérer les administrateurs
                    </a>
                    <a href="statistiques.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        Statistiques globales
                    </a>
                    <a href="parametres.php" class="nav-link">
                        <i class="fas fa-cog"></i>
                        Paramètres système
                    </a>
                    <hr>
                    <a href="../logout.php" class="nav-link text-danger">
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
                    <h1 class="h3 mb-0">Gestion des Universités</h1>
                    <p class="text-muted">Administration des universités du système</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUniversiteModal">
                    <i class="fas fa-plus me-2"></i>
                    Nouvelle université
                </button>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-university me-2"></i>
                        Liste des universités
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Contact</th>
                                    <th>Filières</th>
                                    <th>Étudiants</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($universites)): ?>
                                    <?php foreach ($universites as $universite): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($universite['code']); ?></span>
                                        </td>
                                        <td>
                                            <div>
                                                <small>
                                                    <i class="fas fa-envelope me-1"></i>
                                                    <?php echo htmlspecialchars($universite['email'] ?? 'N/A'); ?>
                                                </small>
                                                <?php if ($universite['telephone']): ?>
                                                <br><small>
                                                    <i class="fas fa-phone me-1"></i>
                                                    <?php echo htmlspecialchars($universite['telephone']); ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $universite['nb_filieres']; ?> filières</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $universite['nb_etudiants']; ?> étudiants</span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editUniversite(<?php echo htmlspecialchars(json_encode($universite)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteUniversite(<?php echo $universite['id']; ?>, '<?php echo htmlspecialchars($universite['nom']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php if (!empty($universite['email'])): ?>
                                                    <?php if (empty($universite['mot_de_passe'])): ?>
                                                        <form method="POST" style="display:inline-block; margin-left:6px;">
                                                            <input type="hidden" name="action" value="create_universite_account">
                                                            <input type="hidden" name="id" value="<?php echo (int)$universite['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-success">
                                                                <i class="fas fa-key me-1"></i> Créer compte
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="badge bg-success ms-2"><i class="fas fa-check me-1"></i>Compte actif</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark ms-2">Email requis</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">
                                            Aucune université trouvée
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ajout/Modification -->
    <div class="modal fade" id="addUniversiteModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nouvelle université</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="create">
                        <input type="hidden" name="id" id="editId">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nom" class="form-label">Nom de l'université *</label>
                                    <input type="text" class="form-control" id="nom" name="nom" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="code" class="form-label">Code université *</label>
                                    <input type="text" class="form-control" id="code" name="code" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="adresse" class="form-label">Adresse</label>
                            <textarea class="form-control" id="adresse" name="adresse" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="telephone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="telephone" name="telephone">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="site_web" class="form-label">Site web</label>
                            <input type="url" class="form-control" id="site_web" name="site_web" placeholder="https://...">
                        </div>

                        <div class="mb-3">
                            <label for="slogan" class="form-label">Slogan</label>
                            <input type="text" class="form-control" id="slogan" name="slogan" placeholder="Ex: Scientia vincere tenebras">
                        </div>

                        <div class="mb-3">
                            <label for="logo" class="form-label">Logo de l'université</label>
                            <input type="file" class="form-control" id="logo" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp">
                            <div class="form-text">Formats: PNG, JPG, SVG, WEBP. Taille conseillée: 300x300px max.</div>
                            <div class="mt-2" id="logoPreviewWrap" style="display:none;">
                                <small class="text-muted d-block mb-1">Logo actuel:</small>
                                <img id="logoPreview" src="" alt="Logo université" style="max-height:64px; max-width:200px; object-fit:contain;">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Suppression -->
    <div class="modal fade" id="deleteUniversiteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer l'université <strong id="deleteUniversiteName"></strong> ?</p>
                    <p class="text-danger"><small>Cette action est irréversible et supprimera toutes les données associées.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteUniversiteId">
                        <button type="submit" class="btn btn-danger">Supprimer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUniversite(universite) {
            document.getElementById('modalTitle').textContent = 'Modifier l\'université';
            document.getElementById('formAction').value = 'update';
            document.getElementById('editId').value = universite.id;
            document.getElementById('nom').value = universite.nom;
            document.getElementById('code').value = universite.code;
            document.getElementById('adresse').value = universite.adresse || '';
            document.getElementById('email').value = universite.email || '';
            document.getElementById('telephone').value = universite.telephone || '';
            document.getElementById('site_web').value = universite.site_web || '';
            document.getElementById('slogan').value = universite.slogan || '';
            // Preview logo if exists
            try {
                var previewWrap = document.getElementById('logoPreviewWrap');
                var preview = document.getElementById('logoPreview');
                if (universite.logo_path) {
                    preview.src = '../' + universite.logo_path;
                    previewWrap.style.display = 'block';
                } else {
                    preview.src = '';
                    previewWrap.style.display = 'none';
                }
            } catch(e) {}
            
            new bootstrap.Modal(document.getElementById('addUniversiteModal')).show();
        }
        
        function deleteUniversite(id, nom) {
            document.getElementById('deleteUniversiteId').value = id;
            document.getElementById('deleteUniversiteName').textContent = nom;
            new bootstrap.Modal(document.getElementById('deleteUniversiteModal')).show();
        }
        
        // Reset modal when closed
        document.getElementById('addUniversiteModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('modalTitle').textContent = 'Nouvelle université';
            document.getElementById('formAction').value = 'create';
            document.getElementById('editId').value = '';
            document.getElementById('nom').value = '';
            document.getElementById('code').value = '';
            document.getElementById('adresse').value = '';
            document.getElementById('email').value = '';
            document.getElementById('telephone').value = '';
            document.getElementById('site_web').value = '';
            document.getElementById('slogan').value = '';
            document.getElementById('logo').value = '';
            var previewWrap = document.getElementById('logoPreviewWrap');
            var preview = document.getElementById('logoPreview');
            if (previewWrap) { previewWrap.style.display = 'none'; }
            if (preview) { preview.src = ''; }
        });
    </script>
</body>
</html> 