<?php
declare(strict_types=1);

$host = 'sql300.infinityfree.com';
$dbname = 'if0_43082226_inventory';
$username = 'if0_43082226';

$password = 'Shaddy1986';

$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    exit('Database connection failed.');
}