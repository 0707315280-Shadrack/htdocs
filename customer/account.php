<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_customer();

/*
 * Customer account page is deliberately defensive: optional V13/V14 tables
 * must never prevent the main account page from opening.
 */
$c = customer_row();
if (!$c) {
    $_SESSION['flash'] = ['type'=>'warning','msg'=>'Please sign in again to open your account.'];
    redirect('/auth/customer-login.php?next=/customer/account.php');
}

$cid = (int)$c['customer_id'];
$errors = [];
$msg = '';

function account_query_count(PDO $pdo, string $sql, array $args = []): int {
    try {
        $q = $pdo->prepare($sql);
        $q->execute($args);
        return (int)$q->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function account_query_rows(PDO $pdo, string $sql, array $args = []): array {
    try {
        $q = $pdo->prepare($sql);
        $q->execute($args);
        return $q->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'profile') {
        $name = trim((string)($_POST['full_name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));

        if ($name === '') {
            $errors[] = 'Full name is required.';
        } else {
            try {
                $q = $pdo->prepare('UPDATE customers SET full_name=?, phone=? WHERE customer_id=?');
                $q->execute([$name, $phone, $cid]);
                $_SESSION['customer']['name'] = $name;
                $_SESSION['customer']['phone'] = $phone;
                $msg = 'Your account details were updated successfully.';
                $fresh = account_query_rows($pdo, 'SELECT * FROM customers WHERE customer_id=? LIMIT 1', [$cid]);
                if ($fresh) $c = $fresh[0];
            } catch (Throwable $e) {
                $errors[] = 'Your account details could not be updated. Please try again.';
            }
        }
    }
}

$counts = [
    'orders' => account_query_count($pdo, 'SELECT COUNT(*) FROM orders WHERE customer_id=?', [$cid]),
    'wishlist' => account_query_count($pdo, 'SELECT COUNT(*) FROM wishlists WHERE customer_id=?', [$cid]),
    'reviews' => account_query_count($pdo, 'SELECT COUNT(*) FROM product_reviews WHERE customer_id=?', [$cid]),
    'messages' => private_unread_count($cid),
    'notifications' => customer_notification_unread_count($cid),
    'following' => account_query_count($pdo, 'SELECT COUNT(*) FROM seller_follows WHERE customer_id=?', [$cid]),
];

$openOrders = account_query_count(
    $pdo,
    "SELECT COUNT(*) FROM orders WHERE customer_id=? AND status NOT IN ('DELIVERED','CANCELLED')",
    [$cid]
);

$totalSpent = 0.0;
try {
    $q = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE customer_id=? AND status<>'CANCELLED'");
    $q->execute([$cid]);
    $totalSpent = (float)$q->fetchColumn();
} catch (Throwable $e) {}

$orders = account_query_rows(
    $pdo,
    'SELECT * FROM orders WHERE customer_id=? ORDER BY created_at DESC LIMIT 5',
    [$cid]
);

$title = 'My Account';
require __DIR__.'/../includes/header.php';
?>
<div class="account-page">
  <div class="section-head account-title">
    <div>
      <span class="eyebrow">MY SHADRIL ACCOUNT</span>
      <h1>Welcome, <?=e((string)($c['full_name'] ?? 'Customer'))?></h1>
      <p>Manage your shopping, orders, messages, reviews and account details here.</p>
    </div>
    <div class="account-top-actions">
      <a class="btn primary" href="/">Continue shopping</a>
      <?=back_button('Previous page')?>
      <a class="btn ghost" href="/auth/logout.php">Sign out</a>
    </div>
  </div>

  <?php if ($msg): ?><div class="flash success"><?=e($msg)?></div><?php endif; ?>
  <?php foreach ($errors as $x): ?><div class="errors"><?=e($x)?></div><?php endforeach; ?>

  <div class="account-stat-grid">
    <div class="account-stat"><small>Total orders</small><strong><?=$counts['orders']?></strong><a href="/customer/orders.php">View orders</a></div>
    <div class="account-stat"><small>In progress</small><strong><?=$openOrders?></strong><a href="/customer/orders.php">Track delivery</a></div>
    <div class="account-stat"><small>Wishlist</small><strong><?=$counts['wishlist']?></strong><a href="/customer/wishlist.php">Open wishlist</a></div>
    <div class="account-stat"><small>Total spent</small><strong><?=money($totalSpent)?></strong><a href="/customer/orders.php">Order history</a></div>
  </div>

  <div class="account-menu-grid">
    <a href="/customer/orders.php"><?=nav_icon('orders')?><strong>Orders & tracking</strong><small><?=$counts['orders']?> order(s) · live status history</small></a>
    <a href="/customer/messages.php"><?=nav_icon('chat')?><strong>Private support chat</strong><small><?=$counts['messages']?> unread management message(s)</small></a>
    <a href="/customer/notifications.php"><?=nav_icon('voucher')?><strong>Order notifications</strong><small><?=$counts['notifications']?> unread update(s)</small></a>
    <a href="/customer/reviews.php"><?=nav_icon('star')?><strong>Ratings & reviews</strong><small><?=$counts['reviews']?> submitted review(s)</small></a>
    <a href="/customer/vouchers.php"><?=nav_icon('voucher')?><strong>Vouchers & offers</strong><small>Current store promotions</small></a>
    <a href="/customer/wishlist.php"><?=nav_icon('heart')?><strong>Wishlist</strong><small><?=$counts['wishlist']?> saved item(s)</small></a>
    <a href="/customer/follow.php"><?=nav_icon('store')?><strong>Follow store</strong><small><?=$counts['following'] ? 'Following Shadril' : 'Follow Shadril Store'?></small></a>
    <a href="/customer/recent.php"><?=nav_icon('recent')?><strong>Recently viewed</strong><small>Your browsing history</small></a>
    <a href="/customer/support.php">🎧<strong>Help & support</strong><small>Open a support ticket or contact customer care</small></a><a href="/customer/settings.php"><?=nav_icon('settings')?><strong>Account settings</strong><small>Profile and password</small></a>
  </div>

  <section class="account-profile panel"><div><span class="eyebrow">PROFILE</span><h2>Your account details</h2><p class="muted">Name, username and email are permanent identity details. Your phone can be changed from Personal Details.</p><a class="btn ghost" href="/customer/personal.php">Manage personal details</a></div><div class="account-profile-summary"><div><small>Full name</small><strong><?=e((string)($c['full_name']??''))?></strong></div><div><small>Username</small><strong><?=e((string)($c['username']??''))?></strong></div><div><small>Email</small><strong><?=e((string)($c['email']??''))?></strong></div><div><small>Phone</small><strong><?=e((string)($c['phone']??'Not provided'))?></strong></div></div></section>

  <section class="account-orders">
    <div class="section-head">
      <div><span class="eyebrow">ORDER ACTIVITY</span><h2>Recent orders</h2></div>
      <a class="text-link" href="/customer/orders.php">View all →</a>
    </div>
    <?php if (!$orders): ?>
      <div class="empty"><h3>No orders yet</h3><p>Your orders will appear here after checkout.</p><a class="btn primary" href="/">Start shopping</a></div>
    <?php else: ?>
      <div class="order-cards">
        <?php foreach ($orders as $o): ?>
          <a class="order-card" href="/customer/order.php?id=<?=e((string)$o['order_id'])?>">
            <div><strong><?=e((string)$o['order_number'])?></strong><small><?=e((string)$o['created_at'])?></small></div>
            <span class="status-pill <?=e(order_status_class((string)$o['status']))?>"><?=e(order_status_label((string)$o['status']))?></span>
            <strong><?=money((float)$o['total_amount'])?></strong>
            <span>Track →</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php require __DIR__.'/../includes/footer.php'; ?>
