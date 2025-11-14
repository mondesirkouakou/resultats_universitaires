<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portail des Résultats Universitaires</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fas fa-graduation-cap me-2"></i>
                Portail Universitaire
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#accueil">Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#services">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-outline-light btn-sm ms-2" href="login.php">Connexion</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="accueil" class="hero-section">
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold text-white mb-4">
                        Consultez vos résultats universitaires en toute simplicité
                    </h1>
                    <p class="lead text-white-50 mb-4">
                        Accédez à vos notes, évaluations et résultats académiques en temps réel. 
                        Une plateforme moderne et sécurisée pour suivre votre progression.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="login.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Se connecter
                        </a>
                        <a href="#services" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-info-circle me-2"></i>
                            En savoir plus
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12">
                    <h2 class="display-5 fw-bold">Nos Services</h2>
                    <p class="lead text-muted">Une plateforme complète pour la gestion académique</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="service-card text-center p-4">
                        <div class="service-icon mb-3">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h4>Étudiants</h4>
                        <p class="text-muted">Consultez vos résultats, notes et évaluations en temps réel</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card text-center p-4">
                        <div class="service-icon mb-3">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <h4>Professeurs</h4>
                        <p class="text-muted">Saisissez et gérez les évaluations de vos étudiants</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card text-center p-4">
                        <div class="service-icon mb-3">
                            <i class="fas fa-university"></i>
                        </div>
                        <h4>Universités</h4>
                        <p class="text-muted">Gérez vos établissements, filières et comptes en toute simplicité</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="bg-light py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-6">
                    <h3 class="fw-bold mb-4">Fonctionnalités principales</h3>
                    <div class="feature-item d-flex align-items-center mb-3">
                        <i class="fas fa-check-circle text-success me-3"></i>
                        <span>Consultation des résultats en temps réel</span>
                    </div>
                    <div class="feature-item d-flex align-items-center mb-3">
                        <i class="fas fa-check-circle text-success me-3"></i>
                        <span>Gestion des évaluations par matière</span>
                    </div>
                    <div class="feature-item d-flex align-items-center mb-3">
                        <i class="fas fa-check-circle text-success me-3"></i>
                        <span>Suivi des périodes académiques</span>
                    </div>
                    <div class="feature-item d-flex align-items-center mb-3">
                        <i class="fas fa-check-circle text-success me-3"></i>
                        <span>Interface moderne et responsive</span>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="stats-card p-4">
                        <h4 class="text-center mb-4">Statistiques</h4>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="stat-item">
                                    <h3 class="text-primary">5000+</h3>
                                    <p class="text-muted">Étudiants</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-item">
                                    <h3 class="text-success">200+</h3>
                                    <p class="text-muted">Professeurs</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Portail des Résultats Universitaires</h5>
                    <p class="text-muted">Une plateforme moderne pour la gestion académique</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted">&copy; 2024 Tous droits réservés</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html> 