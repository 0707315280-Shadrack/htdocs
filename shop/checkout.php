<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/mpesa.php';
require_customer();

$cart = $_SESSION['cart'] ?? [];
$ids = array_keys($cart);
if (!$ids) redirect('/shop/cart.php');
$in = implode(',', array_fill(0, count($ids), '?'));
$s = $pdo->prepare("SELECT * FROM products WHERE product_id IN ($in) AND status='ACTIVE'");
$s->execute($ids);
$items = $s->fetchAll();
$errors=[]; foreach($items as $p){ if((int)$p['quantity_in_stock'] < (int)$cart[$p['product_id']]) $errors[]='Stock changed for '.(string)$p['product_name'].'. Please return to your cart and adjust the quantity.'; }
if(count($items)!==count($ids)) $errors[]='One or more items in your cart are no longer available. Please return to your cart.';
$subtotal = 0.0;
foreach ($items as $p) $subtotal += (float)$p['unit_price'] * (int)$cart[$p['product_id']];

$discount = 0.0;
$coupon = null;
$couponCode = trim((string)($_POST['coupon_code'] ?? ''));
$paymentMethod = strtoupper(trim((string)($_POST['payment_method'] ?? 'CASH_ON_DELIVERY')));
$mpesaEnabled = mpesa_enabled();
$pochiEnabled = (string)store_config_value('pochi_enabled', '0') === '1';
$mpesaEnvironment = strtolower(trim(mpesa_cfg('environment', 'sandbox')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $address = trim((string)($_POST['delivery_address'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    if ($address === '' || $phone === '') $errors[] = 'Delivery address and phone are required.';

    if (!in_array($paymentMethod, ['CASH_ON_DELIVERY', 'MPESA', 'POCHI'], true)) {
        $errors[] = 'Please choose a valid payment method.';
    } elseif ($paymentMethod === 'MPESA' && !$mpesaEnabled) {
        $errors[] = 'Online M-Pesa payment is currently disabled. Please choose another payment method or contact support.';
    } elseif ($paymentMethod === 'POCHI' && !$pochiEnabled) {
        $errors[] = 'Pochi la Biashara payment is currently unavailable. Please choose another payment method.';
    }

    if ($paymentMethod === 'MPESA' && $mpesaEnabled) {
        try {
            mpesa_phone($phone);
            if (mpesa_cfg('shortcode') === '' || mpesa_secret('consumer_key') === '' || mpesa_secret('consumer_secret') === '' || mpesa_secret('passkey') === '') {
                $errors[] = 'M-Pesa configuration is incomplete. Please choose another payment method or contact store support.';
            }
        } catch (Throwable $validationError) {
            $errors[] = 'Enter a valid Kenyan M-Pesa phone number in the delivery phone field.';
        }
    }
    if ($paymentMethod === 'POCHI' && $pochiEnabled && trim((string)store_config_value('pochi_number', '')) === '') {
        $errors[] = 'Pochi payment details are incomplete. Please contact store support or choose another payment method.';
    }

    if ($couponCode !== '') {
        $s = $pdo->prepare("SELECT * FROM coupons WHERE UPPER(code)=UPPER(?) AND status='ACTIVE' AND (expires_at IS NULL OR expires_at>=NOW()) LIMIT 1");
        $s->execute([$couponCode]);
        $coupon = $s->fetch();
        if (!$coupon) {
            $errors[] = 'That voucher is invalid or expired.';
        } elseif ($subtotal < (float)$coupon['minimum_order']) {
            $errors[] = 'This voucher requires a minimum order of '.money((float)$coupon['minimum_order']).'.';
        } elseif ($coupon['discount_type'] === 'PERCENT') {
            $discount = min($subtotal, $subtotal * ((float)$coupon['discount_value'] / 100));
        } else {
            $discount = min($subtotal, (float)$coupon['discount_value']);
        }
    }

    if (!$errors) {
        $total = max(0, $subtotal - $discount);
        if ($paymentMethod === 'MPESA') {
            if ($total < 1) {
                $errors[] = 'The total must be at least KSh 1 for an M-Pesa payment.';
            } elseif (abs($total - round($total)) > 0.001) {
                $errors[] = 'M-Pesa payments must be a whole number of Kenyan shillings. Remove the voucher or choose another payment method.';
            }
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $number = 'SHD-'.date('YmdHis').'-'.random_int(100,999);
            $couponDbCode = $coupon ? (string)$coupon['code'] : null;
            $s = $pdo->prepare("INSERT INTO orders(order_number,customer_id,status,total_amount,delivery_address,phone,payment_method,coupon_code,discount_amount) VALUES(?,?, 'PENDING',?,?,?,?,?,?)");
            $s->execute([$number, (int)$_SESSION['customer_id'], $total, $address, $phone, $paymentMethod, $couponDbCode, $discount]);
            $oid = (int)$pdo->lastInsertId();
            foreach ($items as $p) {
                $q = (int)$cart[$p['product_id']];
                if ($q > (int)$p['quantity_in_stock']) throw new RuntimeException('Stock changed.');
                $pdo->prepare('INSERT INTO order_items(order_id,product_id,quantity,unit_price,unit_cost) VALUES(?,?,?,?,?)')->execute([$oid, $p['product_id'], $q, $p['unit_price'], $p['cost_price'] ?? 0]);
                $pdo->prepare('UPDATE products SET quantity_in_stock=quantity_in_stock-? WHERE product_id=?')->execute([$q, $p['product_id']]);
            }
            if ($coupon) {
                $pdo->prepare('INSERT INTO coupon_redemptions(coupon_id,customer_id,order_id) VALUES(?,?,?)')->execute([(int)$coupon['coupon_id'], (int)$_SESSION['customer_id'], $oid]);
            }
            $pdo->prepare("INSERT INTO order_status_history(order_id,status,notes) VALUES(?,'PENDING','Order placed by customer')")->execute([$oid]);
            $pdo->commit();
            $_SESSION['cart'] = [];
            try { $cq=$pdo->prepare('SELECT email,full_name FROM customers WHERE customer_id=?');$cq->execute([(int)$_SESSION['customer_id']]);$cc=$cq->fetch(); if($cc && filter_var($cc['email'],FILTER_VALIDATE_EMAIL)) send_order_email($cc['email'],$number,'PENDING',money($total),'/customer/order.php?id='.$oid); } catch(Throwable $ignore) {}
            if ($paymentMethod === 'MPESA') {
                // Create the local attempt before contacting Daraja so callbacks can be matched safely.
                $paymentId = 0;
                try {
                    mpesa_ensure_schema();
                    $normalizedPhone = mpesa_phone($phone);
                    $amountToCharge = (int)round($total);
                    $prefix = trim(mpesa_cfg('account_prefix', 'SHADRIL'));
                    if ($prefix === '') $prefix = 'SHADRIL';
                    $accountReference = substr($prefix . '-' . $number, 0, 100);
                    $description = 'Payment for ' . $number;
                    $q = $pdo->prepare("INSERT INTO mpesa_payments (order_id, customer_id, phone, amount, account_reference, transaction_description, transaction_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING')");
                    $q->execute([$oid, (int)$_SESSION['customer_id'], $normalizedPhone, $amountToCharge, $accountReference, $description, mpesa_cfg('transaction_type', 'CustomerPayBillOnline')]);
                    $paymentId = (int)$pdo->lastInsertId();

                    try {
                        $response = mpesa_stk_push($oid, (float)$amountToCharge, $normalizedPhone, $accountReference, $description);
                        $checkoutRequestId = trim((string)($response['CheckoutRequestID'] ?? ''));
                        $merchantRequestId = trim((string)($response['MerchantRequestID'] ?? ''));
                        if ($checkoutRequestId === '') {
                            throw new RuntimeException('Daraja did not return a CheckoutRequestID.');
                        }
                        $q = $pdo->prepare("UPDATE mpesa_payments SET merchant_request_id = ?, checkout_request_id = ?, status = 'PENDING', result_description = NULL WHERE id = ?");
                        $q->execute([$merchantRequestId !== '' ? $merchantRequestId : null, $checkoutRequestId, $paymentId]);
                        $_SESSION['flash'] = ['type'=>'success','msg'=>'Order '.$number.' was created. An M-Pesa prompt was requested. Confirm the amount on your phone and enter your PIN only if the details are correct.'];
                    } catch (Throwable $paymentError) {
                        $reason = substr(trim($paymentError->getMessage()), 0, 255);
                        $definitive = strpos($reason, 'authorization failed') !== false
                            || strpos($reason, 'authorization did not return') !== false
                            || strpos($reason, 'STK request was not accepted') !== false;
                        $state = $definitive ? 'FAILED' : 'PENDING';
                        $safeReason = $definitive
                            ? $reason
                            : 'Daraja request outcome is uncertain. Verify the result before another attempt.';
                        $q = $pdo->prepare("UPDATE mpesa_payments SET status = ?, result_description = ? WHERE id = ? AND status <> 'SUCCESS'");
                        $q->execute([$state, $safeReason, $paymentId]);
                        $_SESSION['flash'] = ['type'=>'warning','msg'=>$definitive
                            ? 'Order '.$number.' was saved, but Daraja rejected the M-Pesa request. Open the order page to review the result before retrying.'
                            : 'Order '.$number.' was saved, but the M-Pesa request result could not be confirmed. Do not retry yet; check the order page or contact support.'];
                    }
                } catch (Throwable $paymentSetupError) {
                    error_log('M-Pesa checkout setup failed for order '.$oid.': '.$paymentSetupError->getMessage());
                    $_SESSION['flash'] = ['type'=>'warning','msg'=>'Order '.$number.' was saved, but the M-Pesa attempt could not be initialized. Do not submit another order; contact store support for assistance.'];
                }
                redirect('/customer/order.php?id='.$oid);
            }

            if ($paymentMethod === 'POCHI') {
                $_SESSION['flash'] = ['type'=>'success','msg'=>'Order '.$number.' was received. Pay manually using the Pochi la Biashara details shown on your order page. The payment will be confirmed by store management.'];
            } else {
                $_SESSION['flash'] = ['type'=>'success','msg'=>'Order '.$number.' has been received successfully. Your order confirmation has been sent to your registered email when delivery is available.'];
            }
            redirect('/customer/order.php?id='.$oid);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'We could not complete the order. Please check stock and voucher details, then try again.';
        }
    }
}

$title='Checkout';
require __DIR__.'/../includes/header.php';
?>
<div class="checkout">
  <div>
    <span class="eyebrow">SECURE CHECKOUT</span><h1>Delivery details</h1>
    <?php foreach ($errors as $e1): ?><div class="errors"><?=e($e1)?></div><?php endforeach; ?>
    <form method="post" class="form">
      <?=csrf_field()?>
      <label>Phone<input name="phone" required value="<?=e($_POST['phone'] ?? ($_SESSION['customer']['phone'] ?? ''))?>"></label>
      <label>Delivery address<textarea name="delivery_address" required><?=e($_POST['delivery_address'] ?? '')?></textarea></label>
      <label>Payment method
        <select name="payment_method" required>
          <option value="CASH_ON_DELIVERY" <?=$paymentMethod==='CASH_ON_DELIVERY'?'selected':''?>>Cash on delivery</option>
          <?php if ($mpesaEnabled): ?><option value="MPESA" <?=$paymentMethod==='MPESA'?'selected':''?>>M-Pesa STK Push<?= $mpesaEnvironment==='sandbox' ? ' (Sandbox test)' : '' ?></option><?php endif; ?>
          <?php if ($pochiEnabled): ?><option value="POCHI" <?=$paymentMethod==='POCHI'?'selected':''?>>Pochi la Biashara (manual confirmation)</option><?php endif; ?>
        </select>
      </label>
      <?php if ($mpesaEnabled && $mpesaEnvironment==='sandbox'): ?><p class="muted">Sandbox mode is for testing only; it does not collect a live customer payment.</p><?php endif; ?>
      <?php if ($pochiEnabled): ?><p class="muted">Pochi payments are confirmed manually by store management; they do not use Daraja STK Push callbacks.</p><?php endif; ?>
      <label>Voucher code <span style="font-weight:400;color:#777">(optional)</span><input name="coupon_code" value="<?=e($couponCode)?>" placeholder="Enter voucher code"></label>
      <button class="btn primary full">Place order · <?=money(max(0,$subtotal-$discount))?></button>
    </form>
  </div>
  <aside class="summary">
    <h2>Order summary</h2>
    <?php foreach ($items as $p): ?><div><span><?=e($p['product_name'])?> × <?=e((string)$cart[$p['product_id']])?></span><strong><?=money((float)$p['unit_price']*(int)$cart[$p['product_id']])?></strong></div><?php endforeach; ?>
    <hr><div><strong>Subtotal</strong><strong><?=money($subtotal)?></strong></div>
    <?php if ($discount>0): ?><div><span>Voucher <?=e($coupon['code'] ?? $couponCode)?></span><strong>-<?=money($discount)?></strong></div><?php endif; ?>
    <div><strong>Total</strong><strong><?=money(max(0,$subtotal-$discount))?></strong></div>
  </aside>
</div>
<?php require __DIR__.'/../includes/footer.php'; ?>
