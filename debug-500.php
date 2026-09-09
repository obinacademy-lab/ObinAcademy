<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo "Step 1: about to require bootstrap.php<br>";
require __DIR__ . '/includes/bootstrap.php';
echo "Step 2: bootstrap.php loaded OK<br>";

echo "Step 3: about to require auth_header.php (used by login.php)<br>";
$pageTitle = 'Debug';
require __DIR__ . '/includes/auth_header.php';
echo "Step 4: auth_header.php loaded OK<br>";
echo "</div></div></div>";
echo "<p style='padding:40px;'>All core includes loaded without a fatal error.</p>";
