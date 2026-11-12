<?php
require is_file(__DIR__.'/../src/Database.php') ? __DIR__.'/../src/Database.php' : '/opt/orbit/src/Database.php';
$schema=file_get_contents(is_file(__DIR__.'/../../database/schema.sql') ? __DIR__.'/../../database/schema.sql' : '/opt/orbit/database/schema.sql');
$pdo=db();
foreach(array_filter(array_map('trim',explode(';',$schema))) as $statement) $pdo->exec($statement);
echo "Migrations completed.\n";
