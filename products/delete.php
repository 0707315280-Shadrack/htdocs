<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

try {
    $stmt = $pdo->prepare('DELETE FROM products WHERE product_id = ?');
    $stmt->execute([$id]);
} catch (PDOException $e) {
    // Product may have transaction history; keep the user-facing page simple.
}

header('Location: index.php');
exit;
