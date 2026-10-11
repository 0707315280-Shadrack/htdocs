<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_login();

$q = trim((string)($_GET['q'] ?? ''));

if ($q !== '') {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE sku_code LIKE ? OR product_name LIKE ? ORDER BY product_name');
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like]);
} else {
    $stmt = $pdo->query('SELECT * FROM products ORDER BY product_name');
}
$products = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Products</title><link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<header class="topbar"><strong>Inventory Management</strong><nav>
<a href="../dashboard.php">Dashboard</a><a href="index.php">Products</a><a href="../transactions/stock_in.php">Stock In</a><a href="../transactions/stock_out.php">Stock Out</a><a href="../transactions/history.php">History</a><a href="../auth/logout.php">Logout</a>
</nav></header>
<main class="container">
<div class="page-head"><div><h1>Products</h1><p>Manage inventory items.</p></div><a class="button" href="add.php">Add Product</a></div>
<form class="search" method="get"><input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search SKU or product name"><button>Search</button></form>
<section class="panel"><div class="table-wrap"><table>
<thead><tr><th>SKU</th><th>Product</th><th>Stock</th><th>Reorder</th><th>Price</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($products as $product): ?>
<tr>
<td><?= htmlspecialchars($product['sku_code']) ?></td>
<td><?= htmlspecialchars($product['product_name']) ?></td>
<td><?= (int)$product['quantity_in_stock'] ?></td>
<td><?= (int)$product['reorder_level'] ?></td>
<td>KES <?= number_format((float)$product['unit_price'], 2) ?></td>
<td><a href="edit.php?id=<?= (int)$product['product_id'] ?>">Edit</a> | <a href="delete.php?id=<?= (int)$product['product_id'] ?>" onclick="return confirm('Delete this product?')">Delete</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></section>
</main>
</body></html>
