<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    exit('Product not found.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku = trim((string)($_POST['sku_code'] ?? ''));
    $name = trim((string)($_POST['product_name'] ?? ''));
    $stock = max(0, (int)($_POST['quantity_in_stock'] ?? 0));
    $reorder = max(0, (int)($_POST['reorder_level'] ?? 0));
    $price = max(0, (float)($_POST['unit_price'] ?? 0));

    try {
        $stmt = $pdo->prepare('UPDATE products SET sku_code=?, product_name=?, quantity_in_stock=?, reorder_level=?, unit_price=? WHERE product_id=?');
        $stmt->execute([$sku, $name, $stock, $reorder, $price, $id]);
        header('Location: index.php');
        exit;
    } catch (PDOException $e) {
        $error = 'Could not update product. Check that the SKU is unique.';
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Edit Product</title><link rel="stylesheet" href="../assets/style.css"></head>
<body><header class="topbar"><strong>Inventory Management</strong><nav><a href="../dashboard.php">Dashboard</a><a href="index.php">Products</a><a href="../auth/logout.php">Logout</a></nav></header>
<main class="container narrow"><h1>Edit Product</h1><?php if ($error): ?><div class="alert danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form class="panel form-grid" method="post">
<label>SKU Code<input name="sku_code" value="<?= htmlspecialchars($product['sku_code']) ?>" required></label>
<label>Product Name<input name="product_name" value="<?= htmlspecialchars($product['product_name']) ?>" required></label>
<label>Stock<input type="number" name="quantity_in_stock" min="0" value="<?= (int)$product['quantity_in_stock'] ?>" required></label>
<label>Reorder Level<input type="number" name="reorder_level" min="0" value="<?= (int)$product['reorder_level'] ?>" required></label>
<label>Unit Price<input type="number" name="unit_price" min="0" step="0.01" value="<?= htmlspecialchars($product['unit_price']) ?>" required></label>
<div><button type="submit">Update Product</button> <a class="button secondary" href="index.php">Cancel</a></div>
</form></main></body></html>
