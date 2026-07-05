<?php
require_once 'vendor/autoload.php';

// Check if Dompdf exists
if (class_exists('Dompdf\Dompdf')) {
    echo "Dompdf is installed! ✅<br>";
    
    // Get version using reflection
    $dompdf = new Dompdf\Dompdf();
    echo "Dompdf loaded successfully!<br>";
} else {
    echo "Dompdf NOT found ❌<br>";
}

echo "<br>Autoloader path: " . realpath('vendor/autoload.php') . "<br>";

// List installed packages
echo "<br>Installed packages:<br>";
if (file_exists('composer.json')) {
    $composer = json_decode(file_get_contents('composer.json'), true);
    if (isset($composer['require'])) {
        foreach ($composer['require'] as $package => $version) {
            echo "- $package: $version<br>";
        }
    }
}
?>