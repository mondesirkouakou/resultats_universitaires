<?php
require_once 'config.php';

// Détruire toutes les variables de session
$_SESSION = array();

// Détruire la session
session_destroy();

// Rediriger vers la page d'accueil
redirect('index.php');
?> 