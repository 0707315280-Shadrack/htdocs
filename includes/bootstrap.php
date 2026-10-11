<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config/database.php';

function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function customer_logged_in(): bool { return !empty($_SESSION['customer_id']); }
function admin_logged_in(): bool { return !empty($_SESSION['admin_id']) && in_array($_SESSION['admin_role'] ?? '', ['Admin','Staff'], true); }
function super_admin(): bool { return admin_logged_in() && !empty($_SESSION['is_super_admin']); }
function require_customer(): void { if (!customer_logged_in()) { $_SESSION['flash'] = ['type'=>'warning','msg'=>'Please sign in to shop.']; header('Location: /auth/customer-login.php'); exit; } }
function require_admin(): void { if (!admin_logged_in()) { http_response_code(403); exit('Not authorised. Administrator access is required.'); } }
function flash(): ?array { $x=$_SESSION['flash']??null; unset($_SESSION['flash']); return $x; }
function redirect(string $path): never { header('Location: '.$path); exit; }
function cart_count(): int { return array_sum(array_map('intval', $_SESSION['cart'] ?? [])); }
function money(float $v): string { return 'KES '.number_format($v,2); }
function csrf_token(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
function check_csrf(): void { if($_SERVER['REQUEST_METHOD']==='POST' && !hash_equals($_SESSION['csrf']??'', (string)($_POST['csrf']??''))) { http_response_code(419); exit('Security token expired. Please go back and try again.'); } }
function customer_row(): ?array { global $pdo; if(!customer_logged_in()) return null; static $c=null; if($c===null){$s=$pdo->prepare('SELECT * FROM customers WHERE customer_id=? LIMIT 1');$s->execute([(int)$_SESSION['customer_id']]);$c=$s->fetch()?:null;} return $c; }
function order_status_label(string $status): string { return ucwords(strtolower(str_replace('_',' ',$status))); }
function order_status_class(string $status): string { return 'status-'.strtolower($status); }

/** Create only missing V10 tables/columns. This is intentionally additive. */
function ensure_shadril_schema(): void {
    global $pdo;
    static $done=false; if($done) return; $done=true;
    try {
        // Core order tables: these may not have been installed when an older V9 package was uploaded.
        $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
          order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          order_number VARCHAR(50) NOT NULL UNIQUE,
          customer_id INT UNSIGNED NOT NULL,
          status ENUM('PENDING','CONFIRMED','PROCESSING','PACKED','DISPATCHED','OUT_FOR_DELIVERY','DELIVERED','CANCELLED') NOT NULL DEFAULT 'PENDING',
          total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
          delivery_address VARCHAR(255) NOT NULL,
          phone VARCHAR(50) NOT NULL,
          payment_method VARCHAR(50) NOT NULL DEFAULT 'CASH_ON_DELIVERY',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX(customer_id), FOREIGN KEY(customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
          order_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          order_id INT UNSIGNED NOT NULL,
          product_id INT UNSIGNED NOT NULL,
          quantity INT UNSIGNED NOT NULL,
          unit_price DECIMAL(12,2) NOT NULL,
          FOREIGN KEY(order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
          FOREIGN KEY(product_id) REFERENCES products(product_id) ON DELETE RESTRICT,
          INDEX(order_id), INDEX(product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS order_status_history (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          order_id INT UNSIGNED NOT NULL,
          status VARCHAR(40) NOT NULL,
          notes VARCHAR(255) NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY(order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
          INDEX(order_id), INDEX(status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Review table used by the customer and admin review pages. Create only if an older installation lacks it.
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS product_reviews (
          review_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          product_id INT UNSIGNED NOT NULL,
          customer_id INT UNSIGNED NOT NULL,
          rating TINYINT UNSIGNED NOT NULL,
          comment TEXT NOT NULL,
          status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY(product_id) REFERENCES products(product_id) ON DELETE CASCADE,
          FOREIGN KEY(customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
          UNIQUE KEY uq_product_customer (product_id, customer_id),
          INDEX(customer_id), INDEX(status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $ignore) {}

        $tables = [
          "CREATE TABLE IF NOT EXISTS password_reset_otps (
            reset_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id INT UNSIGNED NOT NULL,
            otp_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            verified_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(customer_id), INDEX(expires_at),
            FOREIGN KEY(customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
          "CREATE TABLE IF NOT EXISTS seller_follows (
            follow_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id INT UNSIGNED NOT NULL,
            seller_name VARCHAR(150) NOT NULL DEFAULT 'Shadril Shopping Store',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_follow (customer_id, seller_name),
            FOREIGN KEY(customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
          "CREATE TABLE IF NOT EXISTS customer_messages (
            message_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id INT UNSIGNED NOT NULL,
            subject VARCHAR(180) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
            INDEX(customer_id, is_read)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
          "CREATE TABLE IF NOT EXISTS recently_viewed (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL,
            viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_recent (customer_id, product_id),
            FOREIGN KEY(customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
            FOREIGN KEY(product_id) REFERENCES products(product_id) ON DELETE CASCADE
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
          "CREATE TABLE IF NOT EXISTS wishlists (
            wishlist_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_customer_product (customer_id, product_id),
            FOREIGN KEY(customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
            FOREIGN KEY(product_id) REFERENCES products(product_id) ON DELETE CASCADE
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];
        foreach($tables as $sql) { try { $pdo->exec($sql); } catch(Throwable $ignore) {} }

        // Add V10 order columns only if they do not already exist.
        foreach([
          ['orders','coupon_code','ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(40) NULL'],
          ['orders','discount_amount','ALTER TABLE orders ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0']
        ] as $c){
          try { $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");$q->execute([$c[0],$c[1]]);if(!(int)$q->fetchColumn())$pdo->exec($c[2]); } catch(Throwable $ignore) {}
        }
        // coupon_redemptions depends on the existing V3 coupons table. Create it when both parents exist.
        try {
          $q=$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('coupons','orders')");
          if((int)$q->fetchColumn()===2){$pdo->exec("CREATE TABLE IF NOT EXISTS coupon_redemptions (
            redemption_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            coupon_id INT UNSIGNED NOT NULL,
            customer_id INT UNSIGNED NOT NULL,
            order_id INT UNSIGNED NULL,
            redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_coupon_customer (coupon_id, customer_id),
            FOREIGN KEY(coupon_id) REFERENCES coupons(coupon_id) ON DELETE CASCADE,
            FOREIGN KEY(customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
            FOREIGN KEY(order_id) REFERENCES orders(order_id) ON DELETE SET NULL
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");}
        } catch(Throwable $ignore) {}

        // V14 live tracking fields.
        foreach([['orders','tracking_location','ALTER TABLE orders ADD COLUMN tracking_location VARCHAR(160) NULL'],['orders','tracking_eta','ALTER TABLE orders ADD COLUMN tracking_eta DATETIME NULL']] as $c){
          try { $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?"); $q->execute([$c[0],$c[1]]); if(!(int)$q->fetchColumn()) $pdo->exec($c[2]); } catch(Throwable $ignore) {}
        }
        // V13 professional customer management, private chat and public comment replies.
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS customer_private_messages (          message_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,          customer_id INT UNSIGNED NOT NULL,          sender_type ENUM('CUSTOMER','ADMIN') NOT NULL,          sender_id INT UNSIGNED NOT NULL DEFAULT 0,          message TEXT NOT NULL,          is_read TINYINT(1) NOT NULL DEFAULT 0,          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,          INDEX(customer_id,created_at), INDEX(customer_id,is_read)        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $ignore) {}
        foreach([
          ['customers','last_seen_at','ALTER TABLE customers ADD COLUMN last_seen_at DATETIME NULL'],
          ['product_reviews','admin_reply','ALTER TABLE product_reviews ADD COLUMN admin_reply TEXT NULL'],
          ['product_reviews','admin_reply_at','ALTER TABLE product_reviews ADD COLUMN admin_reply_at DATETIME NULL'],
          ['product_reviews','admin_reply_by','ALTER TABLE product_reviews ADD COLUMN admin_reply_by INT NULL']
        ] as $c){
          try { $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?"); $q->execute([$c[0],$c[1]]); if(!(int)$q->fetchColumn()) $pdo->exec($c[2]); } catch(Throwable $ignore) {}
        }
    } catch(Throwable $ignore) {
        // Existing installations continue to run; individual pages will show their normal user-facing errors.
    }
}
ensure_shadril_schema();

function smtp_read($socket): string {
    $data=''; while(($line=fgets($socket,515))!==false){ $data.=$line; if(isset($line[3]) && $line[3]===' ') break; }
    return $data;
}
function smtp_expect($socket,array $codes): void {
    $r=smtp_read($socket); $code=(int)substr(trim($r),0,3); if(!in_array($code,$codes,true)) throw new RuntimeException('SMTP server rejected the request.');
}
function smtp_cmd($socket,string $cmd,array $codes=[250]): void { fwrite($socket,$cmd."\r\n"); smtp_expect($socket,$codes); }
function send_customer_otp(string $email,string $otp): bool {
    $cfgFile=__DIR__.'/../config/mail.php';
    if(!is_file($cfgFile)) return false;
    $cfg=require $cfgFile;
    foreach(['host','port','username','password','from_email'] as $k) if(trim((string)($cfg[$k]??''))==='' || str_contains((string)$cfg[$k],'YOUR_')) return false;
    $host=(string)$cfg['host'];$port=(int)$cfg['port'];$user=(string)$cfg['username'];$pass=(string)$cfg['password'];$from=(string)$cfg['from_email'];$name=(string)($cfg['from_name']??'Shadril Shopping Store');
    try{
        $socket=stream_socket_client('tcp://'.$host.':'.$port,$errno,$errstr,20,STREAM_CLIENT_CONNECT);
        if(!$socket) throw new RuntimeException('SMTP connection failed.');
        stream_set_timeout($socket,20); smtp_expect($socket,[220]); smtp_cmd($socket,'EHLO shadrackinventory.infinityfree.me',[250]);
        smtp_cmd($socket,'STARTTLS',[220]);
        if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('SMTP TLS could not be enabled.');
        smtp_cmd($socket,'EHLO shadrackinventory.infinityfree.me',[250]); smtp_cmd($socket,'AUTH LOGIN',[334]); smtp_cmd($socket,base64_encode($user),[334]); smtp_cmd($socket,base64_encode($pass),[235]);
        smtp_cmd($socket,'MAIL FROM:<'.$from.'>',[250]); smtp_cmd($socket,'RCPT TO:<'.$email.'>',[250,251]); smtp_cmd($socket,'DATA',[354]);
        $subject='Your Shadril Shopping Store password reset code';
        $body="Hello,\r\n\r\nYour Shadril Shopping Store password reset code is: {$otp}\r\n\r\nThis code expires in 10 minutes. If you did not request this password reset, please ignore this message.\r\n\r\nShadril Shopping Store\r\nCustomer support: +254 707 315 280\r\n";
        $headers='From: '.mb_encode_mimeheader($name).' <'.$from.'>\r\nTo: <'.$email.'>\r\nSubject: '.mb_encode_mimeheader($subject).'\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n';
        fwrite($socket,$headers.'\r\n'.$body.'\r\n.\r\n'); smtp_expect($socket,[250]); fwrite($socket,"QUIT\r\n"); fclose($socket); return true;
    }catch(Throwable $e){ if(isset($socket)&&is_resource($socket))fclose($socket); return false; }
}

function customer_notification_unread_count(int $customerId): int {
    global $pdo; try { $q=$pdo->prepare("SELECT COUNT(*) FROM customer_messages WHERE customer_id=? AND is_read=0"); $q->execute([$customerId]); return (int)$q->fetchColumn(); } catch(Throwable $e){ return 0; }
}
function private_unread_count(int $customerId): int {
    global $pdo; try { $q=$pdo->prepare("SELECT COUNT(*) FROM customer_private_messages WHERE customer_id=? AND sender_type='ADMIN' AND is_read=0"); $q->execute([$customerId]); return (int)$q->fetchColumn(); } catch(Throwable $e){ return 0; }
}
function admin_private_unread_count(): int {
    global $pdo; try { $q=$pdo->query("SELECT COUNT(*) FROM customer_private_messages WHERE sender_type='CUSTOMER' AND is_read=0"); return (int)$q->fetchColumn(); } catch(Throwable $e){ return 0; }
}
function nav_icon(string $name): string {
    $icons=[
      'home'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10.8 12 3l9 7.8v9.2a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>',
      'categories'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>',
      'star'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z"/></svg>',
      'new'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5"/></svg>',
      'shop'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16l-1 13H5zM8 7a4 4 0 0 1 8 0M8 11v0M16 11v0"/></svg>',
      'about'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 10v6M12 7v.2"/></svg>',
      'orders'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h14v15H5zM8 3v4M16 3v4M8 11h8M8 15h5"/></svg>',
      'chat'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z"/><path d="M8 10h8M8 13h5"/></svg>',
      'voucher'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z"/><path d="M9 6v12M12 10h5M12 14h3"/></svg>',
      'heart'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 8.8c0 5.2-8.8 10.2-8.8 10.2S3.2 14 3.2 8.8A4.8 4.8 0 0 1 12 6.1a4.8 4.8 0 0 1 8.8 2.7Z"/></svg>',
      'store'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10h16l-1-5H5zM6 10v9h12v-9M9 19v-5h6v5"/></svg>',
      'recent'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3 2M5 5l-2 2M3 7h3"/></svg>',
      'settings'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z"/><path d="m4 13 1-2-1-2 2-2 2 1 2-1 1-3h2l1 3 2 1 2-1 2 2-1 2 1 2-2 2-2-1-2 1-1 3h-2l-1-3-2 1-2 1-2-2Z"/></svg>',
    ]; return $icons[$name]??'';
}

function back_button(string $label='Previous page'): string {
    return '<button type="button" class="back-button" onclick="return shadrilGoBack()" aria-label="'.e($label).'">'.contact_icon('back').' <span>'.e($label).'</span></button>';
}
function contact_icon(string $name): string {
    $icons=[
      'back'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>',
      'phone'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 3.5 9.4 3l2 4.7-2 1.7a15.7 15.7 0 0 0 5.2 5.2l1.7-2 4.7 2-.5 2.8c-.3 1.5-1.7 2.5-3.2 2.3C10.7 19 5 13.3 4.3 6.7 4.1 5.2 5.1 3.8 6.6 3.5Z"/></svg>',
      'whatsapp'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 11.7a8.5 8.5 0 0 1-12.6 7.5L4 20.5l1.4-3.7A8.5 8.5 0 1 1 20.5 11.7Z"/><path d="M9 8.5c.2-.4.4-.5.7-.5h.6c.2 0 .4.1.5.4l.8 1.9c.1.2.1.4-.1.6l-.6.7c.7 1.2 1.7 2.1 3 2.7l.6-.6c.2-.2.4-.2.6-.1l1.8.8c.3.1.4.3.4.6v.5c0 .4-.2.7-.5.8-1 .4-2.2.2-3.2-.3-2.2-1-4.1-2.9-5.1-5.1-.5-1-.7-2.2-.2-3.4Z"/></svg>'
    ];
    return $icons[$name]??'';
}

function product_image(array $p): string {
    $url = trim((string)($p['image_url'] ?? ''));
    if ($url !== '' && !str_ends_with(strtolower(parse_url($url, PHP_URL_PATH) ?: ''), '.svg')) return $url;
    $cat = strtolower(trim((string)($p['category_name'] ?? '')));
    $name = strtolower(trim((string)($p['product_name'] ?? '')));
    $photos = [
        'phones' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=88',
        'fashion' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=900&q=88',
        'shoes' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=88',
        'food & groceries' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=900&q=88',
        'electronics' => 'https://images.unsplash.com/photo-1606220945770-b5b6c2c55bf1?auto=format&fit=crop&w=900&q=88',
        'computers' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=900&q=88',
        'home appliances' => 'https://images.unsplash.com/photo-1585515320310-259814833e62?auto=format&fit=crop&w=900&q=88',
        'beauty' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=900&q=88',
        'baby' => 'https://images.unsplash.com/photo-1515488042361-ee00e0ddd4e4?auto=format&fit=crop&w=900&q=88',
        'sports' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=900&q=88',
        'accessories' => 'https://images.unsplash.com/photo-1523170335258-f5ed11844a49?auto=format&fit=crop&w=900&q=88',
        'tvs' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?auto=format&fit=crop&w=900&q=88',
    ];
    foreach (['shirt','dress','trouser','jacket','t-shirt','handbag'] as $k) if (str_contains($name,$k)) return $photos['fashion'];
    foreach (['shoe','sneaker'] as $k) if (str_contains($name,$k)) return $photos['shoes'];
    foreach (['rice','oil','tea','flour'] as $k) if (str_contains($name,$k)) return $photos['food & groceries'];
    return $photos[$cat] ?? ($url ?: '/assets/favicon.svg');
}
