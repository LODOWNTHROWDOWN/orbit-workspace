<?php
declare(strict_types=1);

require is_file(__DIR__ . '/../src/autoload.php') ? __DIR__ . '/../src/autoload.php' : '/opt/orbit/src/autoload.php';

use Orbit\Database;

$schemaFile = is_file(__DIR__ . '/../../database/schema.sql')
    ? __DIR__ . '/../../database/schema.sql'
    : '/opt/orbit/database/schema.sql';

$pdo = Database::connection();
foreach (array_filter(array_map('trim', explode(';', (string) file_get_contents($schemaFile)))) as $statement) {
    $pdo->exec($statement);
}
echo "Migrations completed.\n";
