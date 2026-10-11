<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_login();

$message = '';
$error = '';
$products = $pdo->query('SELECT product_id, product_name, quantity_in_stock FROM products ORDER BY product_name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty = (int)($_POST['quantity'] ?? 0);

    if ($productId <= 0 || $qty <= 0) {
        $error = 'Select a product and enter a quantity greater than zero.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('UPDATE products SET quantity_in_stock = quantity_in_stock - ? WHERE product_id = ? AND quantity_in_stock >= ?');
            $stmt->execute([$qty, $productId, $qty]);

            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                $error = 'Insufficient stock available for this dispatch.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO transactions (product_id, user_id, transaction_type, quantity) VALUES (?, ?, 'OUT', ?)");
                $stmt->execute([$productId, (int)$_SESSION['user_id'], $qty]);
                $pdo->commit();
                $message = 'Stock dispatched successfully.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Could not record the stock-out transaction.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Stock Out</title><link rel="stylesheet" href="../assets/style.css"></head>
<body><header class="topbar"><strong>Inventory Management</strong><nav><a href="../dashboard.php">Dashboard</a><a href="../products/index.php">Products</a><a href="stock_in.php">Stock In</a><a href="stock_out.php">Stock Out</a><a href="history.php">History</a><a href="../auth/logout.php">Logout</a></nav></header>
<main class="container narrow"><h1>Stock Out</h1><p>Record goods dispatched or sold.</p>
<?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?><?php if ($error): ?><div class="alert danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form class="panel form-grid" method="post">
<label>Product<select name="product_id" required><option value="">Select product</option><?php foreach ($products as $p): ?><option value="<?= (int)$p['product_id'] ?>"><?= htmlspecialchars($p['product_name']) ?> (Available: <?= (int)$p['quantity_in_stock'] ?>)</option><?php endforeach; ?></select></label>
<label>Quantity<input type="number" name="quantity" min="1" required></label>
<button type="submit">Record Stock Out</button>
</form></main></body></html>
