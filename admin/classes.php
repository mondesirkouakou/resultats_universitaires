<?php
require_once '../config.php';

// Vérification des permissions - universités et admins peuvent accéder
if (!isLoggedIn() || (!hasPermission('universite') && !hasPermission('admin'))) {
    redirect('../login.php');
}

$pdo = getDatabaseConnection();
$message = '';
$error = '';
// Scoping: utilisateur université et son ID
$isUniversite = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'universite';
$currentUniversiteId = $_SESSION['user_id'] ?? null;
// Déterminer si l'utilisateur est un administrateur
$isAdmin = hasPermission('admin');

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nom = sanitizeInput($_POST['nom'] ?? '');
        $annee = sanitizeInput($_POST['annee'] ?? '');
        $semestre = sanitizeInput($_POST['semestre'] ?? '');
        $niveau = sanitizeInput($_POST['niveau'] ?? '');
        $filiere_id = (int)($_POST['filiere_id'] ?? 0);
        $capacite = (int)($_POST['capacite'] ?? 30);

        if (empty($nom) || empty($annee) || empty($semestre) || $filiere_id <= 0) {
            $error = 'Veuillez remplir tous les champs obligatoires';
        } else {
            try {
                // Vérifier que la filière appartient à l'université courante
                if ($isUniversite) {
                    $check = $pdo->prepare("SELECT 1 FROM universite_filiere WHERE universite_id = ? AND filiere_id = ? LIMIT 1");
                    $check->execute([$currentUniversiteId, $filiere_id]);
                    if (!$check->fetchColumn()) {
                        throw new Exception("Vous ne pouvez pas créer une classe sur une filière d'une autre université");
                    }
                }
                $stmt = $pdo->prepare("INSERT INTO classes (nom, annee, semestre, niveau, filiere_id, capacite) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nom, $annee, $semestre, $niveau, $filiere_id, $capacite]);
                $message = 'Classe créée avec succès';
            } catch (PDOException $e) {
                $error = 'Erreur lors de la création de la classe : ' . $e->getMessage();
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $nom = sanitizeInput($_POST['nom'] ?? '');
        $annee = sanitizeInput($_POST['annee'] ?? '');
        $semestre = sanitizeInput($_POST['semestre'] ?? '');
        $niveau = sanitizeInput($_POST['niveau'] ?? '');
        $filiere_id = (int)($_POST['filiere_id'] ?? 0);
        $capacite = (int)($_POST['capacite'] ?? 30);

        if ($id <= 0 || empty($nom) || empty($annee) || empty($semestre) || $filiere_id <= 0) {
            $error = 'Données invalides';
        } else {
            try {
                // Vérifier possession de la classe et validité de la nouvelle filière
                if ($isUniversite) {
                    $own = $pdo->prepare("SELECT 1
                        FROM classes c
                        INNER JOIN filieres f ON c.filiere_id = f.id
                        INNER JOIN universite_filiere uf ON f.id = uf.filiere_id
                        WHERE c.id = ? AND uf.universite_id = ? LIMIT 1");
                    $own->execute([$id, $currentUniversiteId]);
                    if (!$own->fetchColumn()) {
                        throw new Exception("Vous n'avez pas l'autorisation de modifier cette classe");
                    }
                    $check = $pdo->prepare("SELECT 1 FROM universite_filiere WHERE universite_id = ? AND filiere_id = ? LIMIT 1");
                    $check->execute([$currentUniversiteId, $filiere_id]);
                    if (!$check->fetchColumn()) {
                        throw new Exception("La filière sélectionnée n'appartient pas à votre université");
                    }
                }
                $stmt = $pdo->prepare("UPDATE classes SET nom = ?, annee = ?, semestre = ?, niveau = ?, filiere_id = ?, capacite = ? WHERE id = ?");
                $stmt->execute([$nom, $annee, $semestre, $niveau, $filiere_id, $capacite, $id]);
                $message = 'Classe mise à jour avec succès';
            } catch (PDOException $e) {
                $error = 'Erreur lors de la mise à jour de la classe : ' . $e->getMessage();
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            try {
                // Vérifier possession avant suppression
                if ($isUniversite) {
                    $own = $pdo->prepare("SELECT 1
                        FROM classes c
                        INNER JOIN filieres f ON c.filiere_id = f.id
                        INNER JOIN universite_filiere uf ON f.id = uf.filiere_id
                        WHERE c.id = ? AND uf.universite_id = ? LIMIT 1");
                    $own->execute([$id, $currentUniversiteId]);
                    if (!$own->fetchColumn()) {
                        throw new Exception("Vous n'avez pas l'autorisation de supprimer cette classe");
                    }
                }
                $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Classe supprimée avec succès';
            } catch (PDOException $e) {
                $error = 'Erreur lors de la suppression de la classe : ' . $e->getMessage();
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// Récupération des classes
$classes = [];
try {
    if ($isUniversite) {
        $stmt = $pdo->prepare("
            SELECT c.*, f.nom as filiere_nom, f.niveau_entree, GROUP_CONCAT(DISTINCT u.nom SEPARATOR ', ') as universites_noms,
                   (SELECT COUNT(*) FROM etudiants e WHERE e.classe_id = c.id) as nb_etudiants
            FROM classes c 
            LEFT JOIN filieres f ON c.filiere_id = f.id 
            LEFT JOIN universite_filiere uf ON f.id = uf.filiere_id
            LEFT JOIN universites u ON uf.universite_id = u.id 
            WHERE uf.universite_id = :uid
            GROUP BY c.id
            ORDER BY c.annee DESC, c.semestre, c.nom
        ");
        $stmt->execute([':uid' => $currentUniversiteId]);
    } else {
        $stmt = $pdo->query("
            SELECT c.*, f.nom as filiere_nom, f.niveau_entree, GROUP_CONCAT(DISTINCT u.nom SEPARATOR ', ') as universites_noms,
                   (SELECT COUNT(*) FROM etudiants e WHERE e.classe_id = c.id) as nb_etudiants
            FROM classes c 
            LEFT JOIN filieres f ON c.filiere_id = f.id 
            LEFT JOIN universite_filiere uf ON f.id = uf.filiere_id
            LEFT JOIN universites u ON uf.universite_id = u.id 
            GROUP BY c.id
            ORDER BY c.annee DESC, c.semestre, c.nom
        ");
    }
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des classes : ' . $e->getMessage();
}

// Récupération des filières pour le formulaire
$filieres = [];
try {
    if ($isUniversite) {
        $stmt = $pdo->prepare("
            SELECT f.id, f.nom, f.niveau_entree, GROUP_CONCAT(DISTINCT u.nom SEPARATOR ', ') as universites_noms
            FROM filieres f 
            INNER JOIN universite_filiere uf ON f.id = uf.filiere_id AND uf.universite_id = :uid
            LEFT JOIN universites u ON uf.universite_id = u.id 
            GROUP BY f.id
            ORDER BY f.nom
        ");
        $stmt->execute([':uid' => $currentUniversiteId]);
    } else {
        $stmt = $pdo->query("
            SELECT f.id, f.nom, f.niveau_entree, GROUP_CONCAT(DISTINCT u.nom SEPARATOR ', ') as universites_noms
            FROM filieres f 
            LEFT JOIN universite_filiere uf ON f.id = uf.filiere_id
            LEFT JOIN universites u ON uf.universite_id = u.id 
            GROUP BY f.id
            ORDER BY f.nom
        ");
    }
    $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des filières : ' . $e->getMessage();
}

// Génération des années académiques
$annee_actuelle = (int)date('Y');
$annees = [];
for ($i = 0; $i < 5; $i++) {
    $startYear = $annee_actuelle - 2 + $i;
    $endYear = $startYear + 1;
    $annees[] = "$startYear-$endYear";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Gestion des Classes - Interface Université</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <link href="../assets/css/style.css" rel="stylesheet" />
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
        .capacity-indicator {
            font-size: 0.8em;
        }
        .semester-badge {
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
                        <h2><i class="fas fa-users me-2"></i>Gestion des Classes - Interface Université</h2>
                        <a href="<?php echo $isAdmin ? 'dashboard.php' : 'universite_dashboard.php'; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Retour au Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Formulaire de création -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Nouvelle Classe</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="row g-3" novalidate>
                                <input type="hidden" name="action" value="create" />
                                
                                <div class="col-md-6">
                                    <label for="nom" class="form-label">Nom de la classe *</label>
                                    <input type="text" class="form-control" id="nom" name="nom" required />
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="annee" class="form-label">Année académique *</label>
                                    <select class="form-select" id="annee" name="annee" required>
                                        <option value="">Sélectionner une année</option>
                                        <?php foreach ($annees as $annee): ?>
                                            <option value="<?= htmlspecialchars($annee) ?>"><?= htmlspecialchars($annee) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="semestre" class="form-label">Semestre *</label>
                                    <select class="form-select" id="semestre" name="semestre" required>
                                        <option value="">Sélectionner un semestre</option>
                                        <?php
                                        $semestres = ['S1'=>'Semestre 1','S2'=>'Semestre 2','S3'=>'Semestre 3','S4'=>'Semestre 4','S5'=>'Semestre 5','S6'=>'Semestre 6'];
                                        foreach ($semestres as $val => $libelle) {
                                            echo '<option value="'.htmlspecialchars($val).'">'.htmlspecialchars($libelle).'</option>';
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="filiere_id" class="form-label">Filière *</label>
                                    <select class="form-select" id="filiere_id" name="filiere_id" required>
                                        <option value="">Sélectionner une filière</option>
                                        <?php foreach ($filieres as $f): ?>
                                            <option value="<?= (int)$f['id'] ?>">
                                                <?= htmlspecialchars($f['nom']) ?> (<?= htmlspecialchars($f['niveau_entree']) ?> - <?= htmlspecialchars($f['universites_noms']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="niveau" class="form-label">Niveau</label>
                                    <select class="form-select" id="niveau" name="niveau">
                                        <option value="">Sélectionner un niveau</option>
                                        <option value="L1">L1 (Licence 1)</option>
                                        <option value="L2">L2 (Licence 2)</option>
                                        <option value="L3">L3 (Licence 3)</option>
                                        <option value="M1">M1 (Master 1)</option>
                                        <option value="M2">M2 (Master 2)</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="capacite" class="form-label">Capacité</label>
                                    <input type="number" class="form-control" id="capacite" name="capacite" min="1" max="200" value="30" />
                                </div>

                                <div class="col-12 text-end">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-plus me-2"></i>Créer la classe
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste des classes -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Liste des Classes</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom</th>
                                            <th>Année</th>
                                            <th>Semestre</th>
                                            <th>Niveau</th>
                                            <th>Filière</th>
                                            <th>Étudiants</th>
                                            <th>Capacité</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($classes as $classe): ?>
                                            <tr>
                                                <td><?= (int)$classe['id'] ?></td>
                                                <td><strong><?= htmlspecialchars($classe['nom']) ?></strong></td>
                                                <td><span class="badge bg-primary"><?= htmlspecialchars($classe['annee']) ?></span></td>
                                                <td><span class="badge bg-secondary semester-badge"><?= htmlspecialchars($classe['semestre']) ?></span></td>
                                                <td><span class="badge bg-info"><?= htmlspecialchars($classe['niveau'] ?? 'N/A') ?></span></td>
                                                <td>
                                                    <small>
                                                        <?= htmlspecialchars($classe['filiere_nom'] ?? 'N/A') ?><br>
                                                        <em class="text-muted"><?= htmlspecialchars($classe['niveau_entree'] ?? '') ?> - <?= htmlspecialchars($classe['universites_noms'] ?? '') ?></em>
                                                    </small>
                                                </td>
                                                <td><span class="badge bg-success"><?= (int)$classe['nb_etudiants'] ?> étudiants</span></td>
                                                <td>
                                                    <?php 
                                                    $capacite = max(1, (int)$classe['capacite']);
                                                    $nb_etudiants = (int)$classe['nb_etudiants'];
                                                    $pourcentage = ($nb_etudiants / $capacite) * 100;
                                                    $color = $pourcentage >= 90 ? 'danger' : ($pourcentage >= 70 ? 'warning' : 'success');
                                                    ?>
                                                    <span class="badge bg-<?= $color ?>"><?= $nb_etudiants ?>/<?= $capacite ?></span><br>
                                                    <small class="text-muted"><?= round($pourcentage, 1) ?>%</small>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary btn-action" 
                                                        onclick='editClasse(<?= json_encode($classe, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger btn-action" 
                                                        onclick="deleteClasse(<?= (int)$classe['id'] ?>, '<?= addslashes(htmlspecialchars($classe['nom'])) ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($classes)): ?>
                                            <tr><td colspan="9" class="text-center text-muted">Aucune classe disponible</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal d'édition -->
        <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Modifier la Classe</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <form method="POST" id="editForm" novalidate>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="update" />
                            <input type="hidden" name="id" id="edit_id" />

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="edit_nom" class="form-label">Nom de la classe *</label>
                                    <input type="text" class="form-control" id="edit_nom" name="nom" required />
                                </div>

                                <div class="col-md-3">
                                    <label for="edit_annee" class="form-label">Année académique *</label>
                                    <select class="form-select" id="edit_annee" name="annee" required>
                                        <option value="">Sélectionner une année</option>
                                        <?php foreach ($annees as $annee): ?>
                                            <option value="<?= htmlspecialchars($annee) ?>"><?= htmlspecialchars($annee) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="edit_semestre" class="form-label">Semestre *</label>
                                    <select class="form-select" id="edit_semestre" name="semestre" required>
                                        <option value="">Sélectionner un semestre</option>
                                        <?php foreach ($semestres as $val => $libelle): ?>
                                            <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($libelle) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="edit_filiere_id" class="form-label">Filière *</label>
                                    <select class="form-select" id="edit_filiere_id" name="filiere_id" required>
                                        <option value="">Sélectionner une filière</option>
                                        <?php foreach ($filieres as $f): ?>
                                            <option value="<?= (int)$f['id'] ?>">
                                                <?= htmlspecialchars($f['nom']) ?> (<?= htmlspecialchars($f['niveau_entree']) ?> - <?= htmlspecialchars($f['universites_noms']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="edit_capacite" class="form-label">Capacité</label>
                                    <input type="number" class="form-control" id="edit_capacite" name="capacite" min="1" max="200" />
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
        <form method="POST" id="deleteForm" style="display:none;">
            <input type="hidden" name="action" value="delete" />
            <input type="hidden" name="id" id="delete_id" />
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editClasse(classe) {
            document.getElementById('edit_id').value = classe.id;
            document.getElementById('edit_nom').value = classe.nom;
            document.getElementById('edit_annee').value = classe.annee;
            document.getElementById('edit_semestre').value = classe.semestre;
            document.getElementById('edit_filiere_id').value = classe.filiere_id;
            document.getElementById('edit_capacite').value = classe.capacite || '';
            document.getElementById('edit_description').value = classe.description || '';

            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        function deleteClasse(id, nom) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer la classe "${nom}" ?`)) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                bootstrap.Alert.getOrCreateInstance(alert).close();
            });
        }, 5000);
    </script>
</body>
</html>
