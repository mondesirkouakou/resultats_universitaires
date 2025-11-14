<?php
require_once '../config.php';

// Auth checks
if (!isLoggedIn()) {
    redirect('../login.php');
}
if (!hasPermission('admin')) {
    redirect('../login.php');
}

$username = $_SESSION['username'] ?? 'Admin';

$message = '';
$error = '';
$db_info = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'test_db') {
        try {
            $pdo = getDatabaseConnection();
            $version = $pdo->query('SELECT VERSION() AS v')->fetch(PDO::FETCH_ASSOC)['v'] ?? 'inconnue';
            $db_info['version'] = $version;
            $counts = [];
            foreach (['universites','filieres','classes','etudiants','professeurs','matieres','notes'] as $tbl) {
                $counts[$tbl] = (int)$pdo->query("SELECT COUNT(*) AS c FROM `{$tbl}`")->fetch(PDO::FETCH_ASSOC)['c'];
            }
            $db_info['counts'] = $counts;
            $message = 'Connexion à la base OK et statistiques récupérées.';
        } catch (Throwable $e) {
            $error = 'Erreur de connexion à la base: ' . $e->getMessage();
        }
    }
}

// Try to prefetch lightweight info for display
try {
    $pdo = getDatabaseConnection();
    $php_version = PHP_VERSION;
    $db_version = $pdo->query('SELECT VERSION() AS v')->fetch(PDO::FETCH_ASSOC)['v'] ?? '—';
} catch (Throwable $e) {
    $php_version = PHP_VERSION;
    $db_version = 'Indisponible';
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Admin Principal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .dashboard-container { min-height: 100vh; background:#f8f9fa; }
        .sidebar { background:white; box-shadow:2px 0 10px rgba(0,0,0,.1); height:100vh; position:fixed; width:280px; z-index:1000; }
        .main-content { margin-left:280px; padding:20px; }
        .card-tile { background:white; border-radius:15px; padding:20px; box-shadow:0 5px 15px rgba(0,0,0,.08); }
        .form-help { font-size:.9rem; color:#6c757d; }
        code { background:#f1f3f5; padding:.1rem .3rem; border-radius:4px; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-4">
            <h4 class="text-primary mb-4">
                <i class="fas fa-cog"></i>
                Paramètres système
            </h4>
            <nav class="nav flex-column">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    Tableau de bord
                </a>
                <a href="universites.php" class="nav-link">
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
                <a href="parametres.php" class="nav-link active">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Paramètres</h1>
                <p class="text-muted">Administration du système</p>
            </div>
            <div class="d-flex align-items-center">
                <a href="dashboard.php" class="btn btn-outline-secondary me-3">
                    <i class="fas fa-arrow-left me-1"></i> Retour au Dashboard
                </a>
                <div class="text-muted">Connecté en tant que <strong><?php echo htmlspecialchars($username); ?></strong></div>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card-tile">
                    <h5 class="mb-3"><i class="fas fa-user-shield text-primary me-2"></i>Mot de passe administrateur</h5>
                    <p class="form-help">Pour des raisons de sécurité, utilisez la page dédiée au changement de mot de passe.
                        Vous serez invité à saisir l'ancien et le nouveau mot de passe.</p>
                    <a href="../change_password.php" class="btn btn-primary">
                        <i class="fas fa-key me-1"></i> Changer mon mot de passe
                    </a>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card-tile">
                    <h5 class="mb-3"><i class="fas fa-database text-success me-2"></i>Diagnostic de la base de données</h5>
                    <form method="post" class="mb-2">
                        <input type="hidden" name="action" value="test_db">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-stethoscope me-1"></i> Tester la connexion et compter les enregistrements
                        </button>
                    </form>
                    <?php if (!empty($db_info)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <tbody>
                                    <tr><th>Version MySQL</th><td><?php echo htmlspecialchars($db_info['version'] ?? '—'); ?></td></tr>
                                    <?php if (!empty($db_info['counts'])): foreach ($db_info['counts'] as $tbl => $cnt): ?>
                                        <tr><th><?php echo htmlspecialchars($tbl); ?></th><td><?php echo number_format((int)$cnt); ?></td></tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-muted">Cliquez sur le bouton ci-dessus pour exécuter un diagnostic rapide.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card-tile">
                    <h5 class="mb-3"><i class="fas fa-info-circle text-info me-2"></i>Informations système</h5>
                    <table class="table table-sm">
                        <tbody>
                            <tr><th>Heure serveur</th><td><?php echo date('d/m/Y H:i:s'); ?></td></tr>
                            <tr><th>Version PHP</th><td><?php echo htmlspecialchars($php_version); ?></td></tr>
                            <tr><th>Version MySQL</th><td><?php echo htmlspecialchars($db_version); ?></td></tr>
                            <tr><th>Chemin racine</th><td><code><?php echo htmlspecialchars(realpath(dirname(__DIR__)) ?: dirname(__DIR__)); ?></code></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card-tile">
                    <h5 class="mb-3"><i class="fas fa-bug text-warning me-2"></i>Outils de debug</h5>
                    <div class="d-grid gap-2">
                        <a href="../debug_login.php" class="btn btn-outline-secondary">
                            <i class="fas fa-sign-in-alt me-1"></i> Diagnostiquer la connexion (admin/prof/étudiant)
                        </a>
                        <a href="../debug_student_login.php" class="btn btn-outline-secondary">
                            <i class="fas fa-user-graduate me-1"></i> Diagnostiquer la connexion étudiant
                        </a>
                        <a href="../debug_session.php" class="btn btn-outline-secondary">
                            <i class="fas fa-id-card me-1"></i> Inspecter la session
                        </a>
                        <a href="../debug_session_vars.php" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-1"></i> Variables de session
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Highlight active link
(function(){
  const current = 'parametres.php';
  document.querySelectorAll('.nav-link').forEach(a => { if (a.getAttribute('href') === current) a.classList.add('active'); });
})();
</script>
</body>
</html>
