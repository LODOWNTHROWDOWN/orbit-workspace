<?php
declare(strict_types=1);
function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;
  $host=getenv('DB_HOST') ?: 'db'; $port=getenv('DB_PORT') ?: '3306';
  $name=getenv('DB_NAME') ?: 'orbit'; $user=getenv('DB_USER') ?: 'orbit'; $pass=getenv('DB_PASSWORD') ?: '';
  $dsn="mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
  $pdo=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
  return $pdo;
}
