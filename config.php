<?php
/**
 * Configuration centralisée pour le portail des résultats universitaires
 */

// --- Configuration de la base de données ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'resultats_universitaires');
define('DB_USER', 'root');
// Mot de passe MySQL (Laragon par défaut: 'root')
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// --- Paramètres globaux de l'application ---
define('APP_NAME', 'Portail des Résultats Universitaires');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/resultats_universitaires');
define('APP_EMAIL', 'contact@universite.fr');

// --- Sessions ---
define('SESSION_NAME', 'resultats_univ_session');
define('SESSION_LIFETIME', 3600);
define('SESSION_PATH', '/');
define('SESSION_DOMAIN', '');
define('SESSION_SECURE', false); // à mettre true en HTTPS
define('SESSION_HTTP_ONLY', true);

// --- Sécurité / Authentification ---
define('PASSWORD_MIN_LENGTH', 6);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);

// --- Uploads ---
define('UPLOAD_MAX_SIZE', 5242880); // 5 MB
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'pdf']);
define('UPLOAD_PATH', 'uploads/');

// --- Emails ---
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_SECURE', 'tls');

// --- Logs ---
define('LOG_ENABLED', true);
define('LOG_PATH', 'logs/');
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR

// --- Notes et coefficients ---
define('MIN_NOTE', 0);
define('MAX_NOTE', 20);
define('PASSING_NOTE', 10);
define('MIN_COEFFICIENT', 0.5);
define('MAX_COEFFICIENT', 5.0);

// --- Périodes académiques ---
define('DEFAULT_ACADEMIC_YEAR', '2024-2025');
define('DEFAULT_SEMESTER', 1);

// --- Données statiques ---
define('DEMO_USERS', [
    'etudiant' => ['username' => 'etudiant', 'password' => '123456', 'type' => 'etudiant'],
    'professeur' => ['username' => 'professeur', 'password' => '123456', 'type' => 'professeur'],
    'admin_principal' => ['username' => 'admin_principal', 'password' => '123456', 'type' => 'admin_principal'],
    'universite' => ['username' => 'universite', 'password' => '123456', 'type' => 'universite'],
    'parent' => ['username' => 'parent', 'password' => '123456', 'type' => 'parent']
]);

define('EVALUATION_TYPES', [
    'examen' => 'Examen',
    'tp' => 'Travaux Pratiques',
    'td' => 'Travaux Dirigés',
    'projet' => 'Projet',
    'oral' => 'Examen Oral'
]);

define('STUDY_LEVELS', [
    'L1' => 'Licence 1',
    'L2' => 'Licence 2',
    'L3' => 'Licence 3',
    'M1' => 'Master 1',
    'M2' => 'Master 2'
]);

define('PROFESSOR_GRADES', [
    'maitre_conf' => 'Maître de conférences',
    'professeur' => 'Professeur',
    'charge_cours' => 'Chargé de cours',
    'vacataire' => 'Vacataire'
]);

define('PARENT_RELATIONS', [
    'pere' => 'Père',
    'mere' => 'Mère',
    'tuteur' => 'Tuteur',
    'autre' => 'Autre'
]);

define('STATUS_ACTIVE', 'actif');
define('STATUS_INACTIVE', 'inactif');
define('STATUS_SUSPENDED', 'suspendu');

define('PERIOD_STATUS_PLANNED', 'planifiee');
define('PERIOD_STATUS_ONGOING', 'en_cours');
define('PERIOD_STATUS_FINISHED', 'terminee');

define('EVALUATION_STATUS_PLANNED', 'planifiee');
define('EVALUATION_STATUS_ONGOING', 'en_cours');
define('EVALUATION_STATUS_FINISHED', 'terminee');

define('INSCRIPTION_STATUS_REGISTERED', 'inscrit');
define('INSCRIPTION_STATUS_ABANDONED', 'abandonne');
define('INSCRIPTION_STATUS_VALIDATED', 'valide');

// --- Initialisation de session ---
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', SESSION_SECURE);

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// --- Erreurs et debug ---
if (!defined('DEBUG_MODE')) define('DEBUG_MODE', true); // ou false en prod

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// --- Fuseau horaire et locale ---
date_default_timezone_set('Europe/Paris');
setlocale(LC_TIME, 'fr_FR.UTF-8', 'French_France.1252');

// ========== FONCTIONS UTILES ==========

function getDatabaseConnection() {
    try {
        // First, try to connect to MySQL server without specifying database
        $connectionString = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        error_log("Attempting to connect with: " . $connectionString . " using user: " . DB_USER);
        $pdo = new PDO($connectionString, DB_USER, DB_PASS, $options);

        // Check if database exists
        $stmt = $pdo->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
        if ($stmt->rowCount() === 0) {
            throw new PDOException("Database '" . DB_NAME . "' does not exist. Please create it first.");
        }

        // Now connect to the specific database
        $dbConnectionString = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        error_log("Connecting to database: " . $dbConnectionString . " using user: " . DB_USER);
        $pdo = new PDO($dbConnectionString, DB_USER, DB_PASS, $options);

        error_log("Successfully connected to database: " . DB_NAME);
        return $pdo;

    } catch (PDOException $e) {
        $error_msg = "Database connection failed: " . $e->getMessage();
        error_log($error_msg);
        error_log("Connection details - Host: " . DB_HOST . ", Database: " . DB_NAME . ", User: " . DB_USER);
        
        // Try to get more specific error information
        if (strpos($e->getMessage(), 'Access denied') !== false) {
            error_log("Authentication failed. Please check your database credentials in config.php");
        } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
            error_log("The database '" . DB_NAME . "' does not exist. Please create it first.");
        } elseif (strpos($e->getMessage(), 'Connection refused') !== false) {
            error_log("Could not connect to MySQL server. Please make sure MySQL is running.");
        }
        
        return null;
    }
}

function validateInput($input, $type = 'string') {
    switch ($type) {
        case 'email': return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
        case 'int': return filter_var($input, FILTER_VALIDATE_INT) !== false;
        case 'float': return filter_var($input, FILTER_VALIDATE_FLOAT) !== false;
        case 'url': return filter_var($input, FILTER_VALIDATE_URL) !== false;
        default: return !empty(trim($input));
    }
}
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}


function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function logActivity($message, $level = 'INFO') {
    if (!LOG_ENABLED) return;

    $logFile = LOG_PATH . date('Y-m-d') . '.log';
    $logMessage = "[" . date('Y-m-d H:i:s') . "] [$level] $message\n";

    if (!is_dir(LOG_PATH)) {
        mkdir(LOG_PATH, 0755, true);
    }

    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function isLoggedIn() {
    // Consider a user logged in if we have a user type and a corresponding user ID
    // Using username here can be unreliable if not set in some edge cases
    return isset($_SESSION['user_type']) && isset($_SESSION['user_id']);
}

function hasPermission($requiredType) {
    return isLoggedIn() && $_SESSION['user_type'] === $requiredType;
}

function formatNote($note) {
    return number_format($note, 2) . '/20';
}

function calculateAverage($notes, $coefficients) {
    if (empty($notes) || empty($coefficients)) return 0;
    
    $totalNote = 0;
    $totalCoeff = 0;

    foreach ($notes as $i => $note) {
        $totalNote += $note * $coefficients[$i];
        $totalCoeff += $coefficients[$i];
    }

    return $totalCoeff > 0 ? $totalNote / $totalCoeff : 0;
}

function isValidNote($note) {
    return is_numeric($note) && $note >= MIN_NOTE && $note <= MAX_NOTE;
}

function isValidCoefficient($coefficient) {
    return is_numeric($coefficient) && $coefficient >= MIN_COEFFICIENT && $coefficient <= MAX_COEFFICIENT;
}
