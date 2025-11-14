<?php
require_once 'config.php';

echo "<h2>Debug Session Variables</h2>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . "\n";
echo "Session Name: " . session_name() . "\n";
echo "\nAll Session Variables:\n";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Expected Variables Check:</h3>";
$expected = ['user_id', 'user_type', 'user_email', 'user_nom', 'user_prenom', 'premiere_connexion'];
foreach ($expected as $var) {
    $status = isset($_SESSION[$var]) ? "✅ SET" : "❌ MISSING";
    $value = isset($_SESSION[$var]) ? $_SESSION[$var] : "N/A";
    echo "<p><strong>$var:</strong> $status - Value: " . htmlspecialchars($value) . "</p>";
}
?>
