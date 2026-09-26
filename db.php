<?php
declare(strict_types=1);

$host = getenv('CLINIC_DB_HOST') ?: '127.0.0.1';
$database = getenv('CLINIC_DB_NAME') ?: 'clinic';
$username = getenv('CLINIC_DB_USER') ?: 'root';
$password = getenv('CLINIC_DB_PASSWORD') ?: '';
$dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

return new PDO($dsn, $username, $password, $options);