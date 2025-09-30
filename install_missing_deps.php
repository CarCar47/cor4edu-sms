<?php
/**
 * Install missing dependencies
 * Adds Slim Twig View and DI Container
 */

echo "Installing missing dependencies...\n";

$composerJson = json_decode(file_get_contents(__DIR__ . '/composer.json'), true);

// Add missing dependencies
$composerJson['require']['slim/twig-view'] = '^3.3';
$composerJson['require']['php-di/php-di'] = '^6.4';

// Write updated composer.json
file_put_contents(__DIR__ . '/composer.json', json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "✅ Updated composer.json with missing dependencies.\n";
echo "Please run: composer install\n";