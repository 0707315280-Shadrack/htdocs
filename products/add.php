<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_login();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku = trim((string)($_POST['sku_code'] ?? ''));
    $name = trim((string)($_POST['product_name'] ?? ''));
    $stock = max(0, (int)($_POST['quantity_in_stock'] ?? 0));
    $reorder = max(0, (int)($_POST['reorder_level'] ?? 0));
    $price = max(0, (float)($_POST['unit_price'] ?? 0));

    if ($sku === '' || $name === '') {
        $error = 'SKU and product name are required.';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO products (sku_code, product_name, quantity_in_stock, reorder_level, unit_price) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$sku, $name, $stock, $reorder, $price]);
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Could not save product. Check that the SKU is unique.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Add Product</title><link rel="stylesheet" href="../assets/style.css"></head>
<body><header class="topbar"><strong>Inventory Management</strong><nav><a href="../dashboard.php">Dashboard</a><a href="index.php">Products</a><a href="../auth/logout.php">Logout</a></nav></header>
<main class="container narrow"><h1>Add Product</h1><?php if ($error): ?><div class="alert danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form class="panel form-grid" method="post">
<label>SKU Code<input name="sku_code" required></label>
<label>Product Name<input name="product_name" required></label>
<label>Opening Stock<input type="number" name="quantity_in_stock" min="0" value="0" required></label>
<label>Reorder Level<input type="number" name="reorder_level" min="0" value="5" required></label>
<label>Unit Price<input type="number" name="unit_price" min="0" step="0.01" value="0.00" required></label>
<div><button type="submit">Save Product</button> <a class="button secondary" href="index.php">Cancel</a></div>
</form></main></body></html>
