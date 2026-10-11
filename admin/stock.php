<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_admin();
$title='Stock Control'; $msg=''; $err='';

if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf();
  $action=$_POST['action']??''; $pid=(int)($_POST['product_id']??0);
  $qty=max(1,(int)($_POST['quantity']??1)); $unitCost=max(0,(float)($_POST['unit_cost']??0));
  try{
    $pdo->beginTransaction();
    $s=$pdo->prepare('SELECT product_id,product_name,quantity_in_stock,status,cost_price FROM products WHERE product_id=? FOR UPDATE');
    $s->execute([$pid]); $p=$s->fetch(); if(!$p) throw new RuntimeException('Product not found.');
    if($action==='in' || $action==='restore'){
      $unitCost=$unitCost>0?$unitCost:(float)$p['cost_price'];
      if($unitCost>0){$pdo->prepare("UPDATE products SET cost_price=?,quantity_in_stock=quantity_in_stock+?,status='ACTIVE' WHERE product_id=?")->execute([$unitCost,$qty,$pid]);}
      else{$pdo->prepare("UPDATE products SET quantity_in_stock=quantity_in_stock+?,status='ACTIVE' WHERE product_id=?")->execute([$qty,$pid]);}
      $pdo->prepare("INSERT INTO transactions(product_id,user_id,transaction_type,quantity,unit_cost) VALUES(?,?, 'IN',?,?)")->execute([$pid,(int)$_SESSION['admin_id'],$qty,$unitCost]);
      audit_log('STOCK IN',"product_id=$pid quantity=$qty action=".($action==='restore'?'RESTORE':'RECEIVE'));
      $msg=$action==='restore' ? $p['product_name'].' restored to IN STOCK and updated on the customer storefront.' : 'Stock IN recorded for '.$p['product_name'].'.';
    }elseif($action==='out'){
      if((int)$p['quantity_in_stock']<$qty) throw new RuntimeException('Stock OUT quantity cannot exceed the current balance.');
      $pdo->prepare('UPDATE products SET quantity_in_stock=quantity_in_stock-? WHERE product_id=?')->execute([$qty,$pid]);
      $pdo->prepare("INSERT INTO transactions(product_id,user_id,transaction_type,quantity,unit_cost) VALUES(?,?, 'OUT',?,?)")->execute([$pid,(int)$_SESSION['admin_id'],$qty,(float)$p['cost_price']]);
      audit_log('STOCK OUT',"product_id=$pid quantity=$qty");
      $msg='Stock OUT recorded. The live customer balance has been updated.';
    }elseif($action==='activate'){
      if((int)$p['quantity_in_stock']<=0) throw new RuntimeException('An item with zero stock needs a quantity before it can be returned to IN STOCK.');
      $pdo->prepare("UPDATE products SET status='ACTIVE' WHERE product_id=?")->execute([$pid]);
      audit_log('STOCK ACTIVATE',"product_id=$pid"); $msg=$p['product_name'].' is now ACTIVE and visible for shopping.';
    }elseif($action==='deactivate'){
      $pdo->prepare("UPDATE products SET status='INACTIVE' WHERE product_id=?")->execute([$pid]);
      audit_log('STOCK DEACTIVATE',"product_id=$pid"); $msg=$p['product_name'].' was marked unavailable on the storefront.';
    }else throw new RuntimeException('Invalid stock action.');
    $pdo->commit();
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$err=$e->getMessage()==='Stock OUT quantity cannot exceed the current balance.'?$e->getMessage():($e->getMessage()==='An item with zero stock needs a quantity before it can be returned to IN STOCK.'?$e->getMessage():'Stock operation could not be completed.');}
}
$products=$pdo->query('SELECT p.product_id,p.product_name,p.sku_code,p.quantity_in_stock,p.status,p.cost_price,p.unit_price,c.name category FROM products p LEFT JOIN categories c ON c.category_id=p.category_id ORDER BY p.product_name')->fetchAll();
$out=array_values(array_filter($products,fn($p)=>(int)$p['quantity_in_stock']<=0 || strtoupper((string)$p['status'])!=='ACTIVE'));
$in=array_values(array_filter($products,fn($p)=>(int)$p['quantity_in_stock']>0 && strtoupper((string)$p['status'])==='ACTIVE'));
$moves=[]; try{$moves=$pdo->query('SELECT t.*,p.product_name,u.username FROM transactions t LEFT JOIN products p ON p.product_id=t.product_id LEFT JOIN users u ON u.user_id=t.user_id ORDER BY t.created_at DESC LIMIT 50')->fetchAll();}catch(Throwable $ignore){}
require __DIR__.'/../includes/header.php';
?>
<div class="admin-layout"><aside class="admin-side"><strong>Management</strong><a href="/admin/dashboard.php">Dashboard</a><a class="active" href="/admin/stock.php">Stock Control</a><a href="/admin/products.php">Products</a><a href="/admin/orders.php">Orders</a><a href="/admin/support.php">Support</a><a href="/admin/analytics.php">Analytics</a><a href="/admin/settings.php">Settings</a><?php if(super_admin()):?><a href="/admin/users.php">Admin & Staff</a><?php endif;?><a href="/admin/system.php">System Health</a><a href="/auth/logout.php">Sign out</a></aside>
<section class="admin-main"><div class="section-head"><div><span class="eyebrow">LIVE DATABASE INVENTORY</span><h1>Stock Control Centre</h1><p>Every movement updates the MySQL product balance used by the customer storefront immediately.</p></div></div>
<?php if($msg):?><div class="flash success"><?=e($msg)?></div><?php endif;?><?php if($err):?><div class="errors"><?=e($err)?></div><?php endif;?>
<div class="kpi-grid"><div class="stat-card"><span>All products</span><strong><?=count($products)?></strong></div><div class="stat-card"><span>In stock</span><strong><?=count($in)?></strong></div><div class="stat-card"><span>Out / unavailable</span><strong><?=count($out)?></strong></div><div class="stat-card"><span>Total units</span><strong><?=number_format(array_sum(array_map(fn($p)=>(int)$p['quantity_in_stock'],$products)))?></strong></div></div>
<div class="stock-tabs"><a href="#out-of-stock">Out of Stock <b><?=count($out)?></b></a><a href="#stock-in">Stock In <b><?=count($in)?></b></a><a href="#stock-out">Stock Out / Movement</a><a href="#history">Movement History</a></div>
<section id="out-of-stock" class="panel"><div class="section-head"><div><span class="eyebrow">ACTION REQUIRED</span><h2>Out of Stock / Unavailable</h2><p>Manage each item independently. Restoring stock changes its status to ACTIVE and makes it available on the customer side.</p></div></div><?php if($out):?><div class="stock-card-grid"><?php foreach($out as $p):?><article class="stock-item-card out"><div><span class="stock-badge danger">OUT / UNAVAILABLE</span><h3><?=e($p['product_name'])?></h3><p><?=e($p['sku_code']??'')?> · <?=e($p['category']??'Uncategorised')?></p><strong>Current balance: <?=e((string)$p['quantity_in_stock'])?></strong></div><form method="post" class="stock-restore-form"><?=csrf_field()?><input type="hidden" name="action" value="restore"><input type="hidden" name="product_id" value="<?=$p['product_id']?>"><label>Units to restore<input type="number" name="quantity" min="1" value="1" required></label><label>Purchase cost / unit<input type="number" name="unit_cost" min="0" step="0.01" value="<?=e((string)$p['cost_price'])?>"></label><button class="btn primary" type="submit">↻ Return to IN STOCK</button></form></article><?php endforeach;?></div><?php else:?><div class="empty"><h3>No out-of-stock products</h3><p>All currently active products have stock.</p></div><?php endif;?></section>
<section id="stock-in" class="panel"><div class="section-head"><div><span class="eyebrow">AVAILABLE INVENTORY</span><h2>All Stock In</h2></div><a class="btn primary" href="#quick-stock-in">＋ Record Stock In</a></div><div class="table-wrap"><table><thead><tr><th>Product</th><th>Category</th><th>Balance</th><th>Cost</th><th>Retail</th><th>Storefront</th><th>Action</th></tr></thead><tbody><?php foreach($in as $p):?><tr><td><strong><?=e($p['product_name'])?></strong><br><small><?=e($p['sku_code']??'')?></small></td><td><?=e($p['category']??'—')?></td><td><?=e((string)$p['quantity_in_stock'])?></td><td><?=money((float)$p['cost_price'])?></td><td><?=money((float)$p['unit_price'])?></td><td><span class="stock-label">IN STOCK</span></td><td><a class="btn small" href="#quick-stock-in">Add stock</a></td></tr><?php endforeach;?></tbody></table></div></section>
<section id="quick-stock-in" class="stock-control-grid"><section class="panel stock-movement-card"><h2 class="movement-in">＋ Stock In</h2><p>Receive new stock or replenish an existing item.</p><form method="post" class="form"><?=csrf_field()?><input type="hidden" name="action" value="in"><label>Product<select name="product_id" required><?php foreach($products as $p):?><option value="<?=$p['product_id']?>"><?=e($p['product_name'])?> — current <?=$p['quantity_in_stock']?></option><?php endforeach;?></select></label><label>Quantity<input type="number" name="quantity" min="1" value="1" required></label><label>Purchase cost per unit (KES)<input type="number" step="0.01" min="0" name="unit_cost" value="0"><small>Saving purchase cost improves profit and margin calculations.</small></label><button class="btn primary">Record Stock In</button></form></section><section id="stock-out" class="panel stock-movement-card stock-out-card"><h2 class="movement-out">− Stock Out</h2><p>Remove physical stock. The storefront balance changes with it.</p><form method="post" class="form"><?=csrf_field()?><input type="hidden" name="action" value="out"><label>Product<select name="product_id" required><?php foreach($products as $p):?><option value="<?=$p['product_id']?>"><?=e($p['product_name'])?> — current <?=$p['quantity_in_stock']?></option><?php endforeach;?></select></label><label>Quantity<input type="number" name="quantity" min="1" value="1" required></label><button class="btn">Record Stock Out</button></form></section></section>
<section id="history" class="panel"><div class="section-head"><div><span class="eyebrow">AUDITABLE MOVEMENTS</span><h2>Stock Movement History</h2></div></div><div class="table-wrap"><table><thead><tr><th>Date</th><th>Product</th><th>Movement</th><th>Quantity</th><th>Unit cost</th><th>Admin</th></tr></thead><tbody><?php foreach($moves as $m):?><tr><td><?=e($m['created_at'])?></td><td><?=e($m['product_name']??'Deleted product')?></td><td class="<?=strtoupper($m['transaction_type'])==='OUT'?'movement-out':'movement-in'?>"><strong><?=e($m['transaction_type'])?></strong></td><td><?=e((string)$m['quantity'])?></td><td><?=money((float)($m['unit_cost']??0))?></td><td><?=e($m['username']??'Management')?></td></tr><?php endforeach;?></tbody></table></div></section>
</section></div><?php require __DIR__.'/../includes/footer.php';?>
