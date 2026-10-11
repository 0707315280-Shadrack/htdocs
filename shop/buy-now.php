<?php
require_once __DIR__.'/../includes/bootstrap.php';
$id=(int)($_GET['id']??0);
if(!customer_logged_in()){redirect('/auth/customer-login.php?next='.urlencode('/shop/buy-now.php?id='.$id));}
$s=$pdo->prepare("SELECT product_id,quantity_in_stock,status FROM products WHERE product_id=? LIMIT 1");$s->execute([$id]);$p=$s->fetch();
if(!$p || $p['status']!=='ACTIVE' || (int)$p['quantity_in_stock']<=0){$_SESSION['flash']=['type'=>'warning','msg'=>'This item is currently out of stock.'];redirect('/shop/product.php?id='.$id);}
$_SESSION['cart'][$id]=min(1,(int)$p['quantity_in_stock']);$_SESSION['cart_added_at'][$id]=time();redirect('/shop/checkout.php');
