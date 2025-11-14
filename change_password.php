<?php
require_once 'config.php';
require_once 'includes/user_accounts.php';

// Vérifier que l'utilisateur est connecté et que c'est sa première connexion
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || 
    ($_SESSION['user_type'] !== 'etudiant' && $_SESSION['user_type'] !== 'professeur' && $_SESSION['user_type'] !== 'universite')) {
    redirect('student_login.php');
}

// Récupérer les données utilisateur depuis user_data si les variables individuelles n'existent pas
if (!isset($_SESSION['user_email']) && isset($_SESSION['user_data']['email'])) {
    $_SESSION['user_email'] = $_SESSION['user_data']['email'];
}
if (!isset($_SESSION['user_nom']) && isset($_SESSION['user_data']['nom'])) {
    $_SESSION['user_nom'] = $_SESSION['user_data']['nom'];
}
if (!isset($_SESSION['user_prenom']) && isset($_SESSION['user_data']['prenom'])) {
    $_SESSION['user_prenom'] = $_SESSION['user_data']['prenom'];
}

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Veuillez remplir tous les champs';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Les nouveaux mots de passe ne correspondent pas';
    } elseif (strlen($new_password) < 8) {
        $error = 'Le nouveau mot de passe doit contenir au moins 8 caractères';
    } else {
        $pdo = getDatabaseConnection();
        
        // Vérifier l'ancien mot de passe selon le type
        if ($_SESSION['user_type'] === 'universite') {
            $result = authenticateUniversite($pdo, $_SESSION['user_email'], $current_password);
        } else {
            $result = authenticateUser($pdo, $_SESSION['user_email'], $current_password);
        }
        
        if (!$result['success']) {
            $error = 'Mot de passe actuel incorrect';
        } else {
            // Changer le mot de passe
            $changeResult = changePassword($pdo, $_SESSION['user_id'], $_SESSION['user_type'], $new_password);
            
            if ($changeResult['success']) {
                $_SESSION['premiere_connexion'] = false;
                $message = 'Mot de passe modifié avec succès ! Vous allez être redirigé vers votre tableau de bord.';
                
                // Debug: Log du changement de mot de passe
                error_log("DEBUG CHANGE_PASSWORD - Mot de passe changé pour: " . $_SESSION['user_email'] . " (Type: " . $_SESSION['user_type'] . ")");
                
                // Redirection immédiate après changement de mot de passe
                if ($_SESSION['user_type'] === 'etudiant') {
                    $dashboard = 'student_dashboard.php';
                } elseif ($_SESSION['user_type'] === 'professeur') {
                    $dashboard = 'professor_dashboard.php';
                } else { // universite
                    $dashboard = 'admin/universite_dashboard.php';
                }
                header("Location: $dashboard");
                exit();
                
                // Redirection automatique après 3 secondes
                // (facultatif) maintenir un refresh si nécessaire
            } else {
                $error = $changeResult['message'];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer le mot de passe - Système Universitaire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .change-password-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }
        .change-password-header {
            background: linear-gradient(135deg, #FF6B6B, #ee5a52);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .form-control:focus {
            border-color: #FF6B6B;
            box-shadow: 0 0 0 0.2rem rgba(255, 107, 107, 0.25);
        }
        .btn-change {
            background: linear-gradient(135deg, #FF6B6B, #ee5a52);
            border: none;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-change:hover {
            background: linear-gradient(135deg, #ee5a52, #FF6B6B);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .password-requirements {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .password-requirements li {
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="change-password-container">
                    <div class="change-password-header">
                        <h2><i class="fas fa-key me-3"></i>Première connexion</h2>
                        <p class="mb-0">Veuillez changer votre mot de passe temporaire</p>
                        <small class="d-block mt-2 opacity-75">
                            Bonjour <?php echo htmlspecialchars($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']); ?>
                        </small>
                    </div>
                    
                    <div class="p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="current_password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Mot de passe actuel (temporaire)
                                </label>
                                <input type="password" class="form-control form-control-lg" id="current_password" name="current_password" required>
                                <div class="invalid-feedback">
                                    Veuillez saisir votre mot de passe temporaire.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label">
                                    <i class="fas fa-key me-2"></i>Nouveau mot de passe
                                </label>
                                <input type="password" class="form-control form-control-lg" id="new_password" name="new_password" required minlength="8">
                                <div class="invalid-feedback">
                                    Le mot de passe doit contenir au moins 8 caractères.
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-check me-2"></i>Confirmer le nouveau mot de passe
                                </label>
                                <input type="password" class="form-control form-control-lg" id="confirm_password" name="confirm_password" required minlength="8">
                                <div class="invalid-feedback">
                                    Veuillez confirmer votre nouveau mot de passe.
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title text-muted">
                                            <i class="fas fa-info-circle me-2"></i>Exigences du mot de passe
                                        </h6>
                                        <ul class="password-requirements mb-0">
                                            <li>Au moins 8 caractères</li>
                                            <li>Mélange de lettres, chiffres et symboles recommandé</li>
                                            <li>Évitez les mots de passe trop simples</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg btn-change">
                                    <i class="fas fa-save me-2"></i>Changer le mot de passe
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <a href="student_login.php" class="text-muted text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>Retour à la connexion
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Vérification que les mots de passe correspondent
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Les mots de passe ne correspondent pas');
            } else {
                this.setCustomValidity('');
            }
        });

        // Bootstrap form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

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
