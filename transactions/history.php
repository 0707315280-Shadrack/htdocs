<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_login();

$sql = 'SELECT t.transaction_id, p.sku_code, p.product_name, t.transaction_type, t.quantity, u.username, t.created_at
        FROM transactions t
        JOIN products p ON p.product_id = t.product_id
        JOIN users u ON u.user_id = t.user_id
        ORDER BY t.created_at DESC, t.transaction_id DESC';
$rows = $pdo->query($sql)->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Transaction History</title><link rel="stylesheet" href="../assets/style.css"></head>
<body><header class="topbar"><strong>Inventory Management</strong><nav><a href="../dashboard.php">Dashboard</a><a href="../products/index.php">Products</a><a href="stock_in.php">Stock In</a><a href="stock_out.php">Stock Out</a><a href="history.php">History</a><a href="../auth/logout.php">Logout</a></nav></header>
<main class="container"><h1>Transaction History</h1><section class="panel"><div class="table-wrap"><table>
<thead><tr><th>ID</th><th>SKU</th><th>Product</th><th>Type</th><th>Quantity</th><th>User</th><th>Date</th></tr></thead>
<tbody><?php foreach ($rows as $row): ?><tr>
<td><?= (int)$row['transaction_id'] ?></td><td><?= htmlspecialchars($row['sku_code']) ?></td><td><?= htmlspecialchars($row['product_name']) ?></td>
<td><span class="status <?= $row['transaction_type'] === 'IN' ? 'ok' : 'low' ?>"><?= htmlspecialchars($row['transaction_type']) ?></span></td>
<td><?= (int)$row['quantity'] ?></td><td><?= htmlspecialchars($row['username']) ?></td><td><?= htmlspecialchars($row['created_at']) ?></td>
</tr><?php endforeach; ?></tbody></table></div></section></main></body></html>
