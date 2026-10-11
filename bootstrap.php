<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secureCookie = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    // Set secure session flags before PHP creates the session cookie.
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if ($secureCookie) ini_set('session.cookie_secure', '1');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secureCookie,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', $secureCookie, true);
    }
    session_start();
}
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
function store_config_value(string $key,string $default=''): string { global $pdo; try{$q=$pdo->prepare('SELECT config_value FROM store_config WHERE config_key=? LIMIT 1');$q->execute([$key]);$v=$q->fetchColumn();return $v!==false?(string)$v:$default;}catch(Throwable $e){return $default;} }
function customer_care_phone(): string { return store_config_value('customer_care_phone','+254707315280'); }
function customer_care_whatsapp(): string { return preg_replace('/\D+/','',store_config_value('customer_care_whatsapp','254707315280')); }
function product_is_available(array $p): bool { return strtoupper((string)($p['status'] ?? 'ACTIVE')) === 'ACTIVE' && (int)($p['quantity_in_stock'] ?? 0) > 0; }
function csrf_token(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
function check_csrf(): void { if($_SERVER['REQUEST_METHOD']==='POST' && !hash_equals($_SESSION['csrf']??'', (string)($_POST['csrf']??''))) { http_response_code(419); exit('Security token expired. Please go back and try again.'); } }
function customer_row(): ?array { global $pdo; if(!customer_logged_in()) return null; static $c=null; if($c===null){$s=$pdo->prepare('SELECT * FROM customers WHERE customer_id=? LIMIT 1');$s->execute([(int)$_SESSION['customer_id']]);$c=$s->fetch()?:null;} return $c; }
function order_status_label(string $status): string { return ucwords(strtolower(str_replace('_',' ',$status))); }
function order_status_class(string $status): string { return 'status-'.strtolower($status); }

/** Create only missing V10 tables/columns. This is intentionally additive. */
function ensure_shadril_schema(bool $force=false): void {
    global $pdo;
    static $done=false; if($done && !$force) return; $done=true;
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
        // V17 professional account, admin recovery, preferences and cart reminders.
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS admin_password_resets (reset_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,token_hash VARCHAR(255) NOT NULL,expires_at DATETIME NOT NULL,used_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(user_id),INDEX(expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $ignore) {}
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS customer_preferences (customer_id INT UNSIGNED PRIMARY KEY,email_order_updates TINYINT(1) NOT NULL DEFAULT 1,email_cart_reminders TINYINT(1) NOT NULL DEFAULT 1,marketing_email TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $ignore) {}
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS cart_reminders (reminder_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,customer_id INT UNSIGNED NOT NULL,product_id INT UNSIGNED NOT NULL,reminded_at DATETIME NOT NULL,UNIQUE KEY uq_cart_reminder(customer_id,product_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $ignore) {}
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS store_config (config_key VARCHAR(60) PRIMARY KEY,config_value VARCHAR(255) NOT NULL DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");$pdo->exec("INSERT IGNORE INTO store_config(config_key,config_value) VALUES('customer_care_phone','+254707315280'),('customer_care_whatsapp','254707315280')"); } catch(Throwable $ignore) {}
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS store_social_links (link_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,platform VARCHAR(40) NOT NULL UNIQUE,url VARCHAR(255) NOT NULL DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $ignore) {}
        try { foreach([['store_social_links','facebook',''],['store_social_links','twitter',''],['store_social_links','linkedin',''],['store_social_links','instagram',''],['store_social_links','tiktok','']] as $x){$q=$pdo->prepare('INSERT IGNORE INTO store_social_links(platform,url) VALUES(?,?)');$q->execute([$x[1],$x[2]]);} } catch(Throwable $ignore) {}
        foreach([['users','email','ALTER TABLE users ADD COLUMN email VARCHAR(150) NULL'],['users','phone','ALTER TABLE users ADD COLUMN phone VARCHAR(50) NULL'],['users','updated_at','ALTER TABLE users ADD COLUMN updated_at DATETIME NULL'],['customers','updated_at','ALTER TABLE customers ADD COLUMN updated_at DATETIME NULL']] as $c){ try{$q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");$q->execute([$c[0],$c[1]]);if(!(int)$q->fetchColumn())$pdo->exec($c[2]);}catch(Throwable $ignore){} }
        foreach([
          ['customers','last_seen_at','ALTER TABLE customers ADD COLUMN last_seen_at DATETIME NULL'],
          ['product_reviews','admin_reply','ALTER TABLE product_reviews ADD COLUMN admin_reply TEXT NULL'],
          ['product_reviews','admin_reply_at','ALTER TABLE product_reviews ADD COLUMN admin_reply_at DATETIME NULL'],
          ['product_reviews','admin_reply_by','ALTER TABLE product_reviews ADD COLUMN admin_reply_by INT NULL']
        ] as $c){
          try { $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?"); $q->execute([$c[0],$c[1]]); if(!(int)$q->fetchColumn()) $pdo->exec($c[2]); } catch(Throwable $ignore) {}
        }
        // V23 analytics + health: additive fields and diagnostic tables.
        foreach([
          ['products','cost_price','ALTER TABLE products ADD COLUMN cost_price DECIMAL(12,2) NOT NULL DEFAULT 0'],
          ['transactions','unit_cost','ALTER TABLE transactions ADD COLUMN unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0'],
          ['order_items','unit_cost','ALTER TABLE order_items ADD COLUMN unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0']
        ] as $c){ try{$q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");$q->execute([$c[0],$c[1]]);if(!(int)$q->fetchColumn())$pdo->exec($c[2]);}catch(Throwable $ignore){} }
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS system_health_log (health_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NULL,action VARCHAR(120) NOT NULL,result VARCHAR(30) NOT NULL DEFAULT 'INFO',details TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(created_at),INDEX(action),INDEX(result)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $ignore) {}
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS system_alerts (alert_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,fingerprint CHAR(64) NOT NULL UNIQUE,component VARCHAR(120) NOT NULL,severity ENUM('INFO','WARNING','HIGH','CRITICAL') NOT NULL DEFAULT 'HIGH',message TEXT NOT NULL,details TEXT NULL,status ENUM('OPEN','ACKNOWLEDGED','RESOLVED') NOT NULL DEFAULT 'OPEN',first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,notified_at DATETIME NULL,resolved_at DATETIME NULL,INDEX(status),INDEX(severity),INDEX(last_seen_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $ignore) {}
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS security_events (event_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_type VARCHAR(80) NOT NULL,severity ENUM('INFO','WARNING','HIGH','CRITICAL') NOT NULL DEFAULT 'INFO',details TEXT NULL,ip_address VARCHAR(64) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(created_at),INDEX(event_type),INDEX(severity)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $ignore) {}
        // V22 professional operations: audit trail, support tickets and saved store controls.
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS admin_audit_log (audit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NULL,action VARCHAR(120) NOT NULL,details TEXT NULL,ip_address VARCHAR(64) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(user_id),INDEX(created_at),INDEX(action)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $ignore) {}
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS support_tickets (ticket_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,customer_id INT UNSIGNED NOT NULL,subject VARCHAR(180) NOT NULL,message TEXT NOT NULL,status ENUM('OPEN','IN_PROGRESS','RESOLVED','CLOSED') NOT NULL DEFAULT 'OPEN',priority ENUM('LOW','NORMAL','HIGH') NOT NULL DEFAULT 'NORMAL',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(customer_id),INDEX(status),INDEX(updated_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $ignore) {}
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS support_replies (reply_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,ticket_id BIGINT UNSIGNED NOT NULL,sender_type ENUM('CUSTOMER','ADMIN') NOT NULL,sender_id INT UNSIGNED NOT NULL DEFAULT 0,message TEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(ticket_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $ignore) {}
        try { $pdo->exec("INSERT IGNORE INTO store_config(config_key,config_value) VALUES('support_email','otumas18@gmail.com'),('store_tagline','Shop smart. Live better.'),('cart_reminder_hours','24'),('order_email_enabled','1')"); } catch(Throwable $ignore) {}
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS support_contact_log (contact_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,admin_id INT UNSIGNED NULL,customer_id INT UNSIGNED NULL,channel ENUM('PHONE','WHATSAPP','EMAIL','PRIVATE_CHAT') NOT NULL,reference_value VARCHAR(255) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(customer_id),INDEX(channel),INDEX(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $ignore) {}
    } catch(Throwable $ignore) {
        // Existing installations continue to run; individual pages will show their normal user-facing errors.
    }
}
ensure_shadril_schema();
process_cart_reminders();

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
        $headers="From: ".mb_encode_mimeheader($name)." <".$from.">\r\nTo: <".$email.">\r\nSubject: ".mb_encode_mimeheader($subject)."\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        fwrite($socket,$headers."\r\n".$body."\r\n.\r\n"); smtp_expect($socket,[250]); fwrite($socket,"QUIT\r\n"); fclose($socket); return true;
    }catch(Throwable $e){ if(isset($socket)&&is_resource($socket))fclose($socket); return false; }
}

function send_store_email(string $email,string $subject,string $html): bool {
    $cfgFile=__DIR__.'/../config/mail.php'; if(!is_file($cfgFile)) return false; $cfg=require $cfgFile;
    foreach(['host','port','username','password','from_email'] as $k) if(trim((string)($cfg[$k]??''))==='' || str_contains((string)$cfg[$k],'YOUR_')) return false;
    $host=(string)$cfg['host'];$port=(int)$cfg['port'];$user=(string)$cfg['username'];$pass=(string)$cfg['password'];$from=(string)$cfg['from_email'];$name=(string)($cfg['from_name']??'Shadril Shopping Store');$socket=null;
    try{$socket=stream_socket_client('tcp://'.$host.':'.$port,$errno,$errstr,20,STREAM_CLIENT_CONNECT);if(!$socket)throw new RuntimeException('SMTP connection failed');stream_set_timeout($socket,20);smtp_expect($socket,[220]);smtp_cmd($socket,'EHLO shadrackinventory.infinityfree.me',[250]);smtp_cmd($socket,'STARTTLS',[220]);if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('TLS failed');smtp_cmd($socket,'EHLO shadrackinventory.infinityfree.me',[250]);smtp_cmd($socket,'AUTH LOGIN',[334]);smtp_cmd($socket,base64_encode($user),[334]);smtp_cmd($socket,base64_encode($pass),[235]);smtp_cmd($socket,'MAIL FROM:<'.$from.'>',[250]);smtp_cmd($socket,'RCPT TO:<'.$email.'>',[250,251]);smtp_cmd($socket,'DATA',[354]);$headers="From: ".mb_encode_mimeheader($name)." <".$from.">\r\nTo: <".$email.">\r\nSubject: ".mb_encode_mimeheader($subject)."\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";fwrite($socket,$headers."\r\n".$html."\r\n.\r\n");smtp_expect($socket,[250]);fwrite($socket,"QUIT\r\n");fclose($socket);return true;}catch(Throwable $e){if(is_resource($socket))fclose($socket);return false;}
}
function store_email_template(string $eyebrow,string $title,string $body,string $buttonText='',string $buttonUrl=''): string {
 $btn=$buttonText&&$buttonUrl?'<p style="margin:24px 0"><a href="'.e($buttonUrl).'" style="background:#ff6b00;color:#fff;padding:13px 20px;border-radius:9px;text-decoration:none;font-weight:700">'.e($buttonText).'</a></p>':'';
 return '<!doctype html><html><body style="margin:0;background:#f5f5f5;font-family:Arial,sans-serif;color:#171717"><div style="max-width:620px;margin:25px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px #0001"><div style="background:#111;padding:20px;text-align:center"><img src="https://shadrackinventory.infinityfree.me/assets/shadril-logo.png" width="86" alt="Shadril Shopping Store"><div style="color:#fff;font-size:20px;font-weight:800;margin-top:8px">Shadril Shopping Store</div></div><div style="padding:30px"><div style="color:#ff6b00;font-size:12px;font-weight:800;letter-spacing:1px">'.e($eyebrow).'</div><h1 style="margin:8px 0 15px;font-size:27px">'.e($title).'</h1><div style="font-size:15px;line-height:1.7">'.$body.'</div>'.$btn.'<p style="color:#777;font-size:12px;border-top:1px solid #eee;padding-top:18px">Shadril Shopping Store · Kenya · Customer care +254 707 315 280</p></div></div></body></html>';
}
function send_customer_otp_html(string $email,string $otp): bool { return send_store_email($email,'Your Shadril Shopping Store OTP',store_email_template('ACCOUNT SECURITY','Your password reset code','<p>Your one-time verification code is:</p><div style="font-size:36px;letter-spacing:8px;font-weight:900;color:#ff6b00;text-align:center;padding:16px 0">'.e($otp).'</div><p>This code expires in <strong>10 minutes</strong>. If you did not request this, you can ignore this email.</p>')); }
function send_order_email(string $email,string $orderNumber,string $status,string $total,string $orderUrl): bool { return send_store_email($email,'Order '.$orderNumber.' — '.order_status_label($status),store_email_template('ORDER UPDATE','Your order is '.$status,'<p>Your order <strong>'.e($orderNumber).'</strong> has been received/updated.</p><p><strong>Status:</strong> '.e(order_status_label($status)).'<br><strong>Total:</strong> '.e($total).'</p><p>You can follow the same tracking timeline online whenever the status changes.</p>','View order tracking','https://shadrackinventory.infinityfree.me'.$orderUrl)); }
function customer_notification_unread_count(int $customerId): int {
    global $pdo; try { $q=$pdo->prepare("SELECT COUNT(*) FROM customer_messages WHERE customer_id=? AND is_read=0"); $q->execute([$customerId]); return (int)$q->fetchColumn(); } catch(Throwable $e){ return 0; }
}
function private_unread_count(int $customerId): int {
    global $pdo; try { $q=$pdo->prepare("SELECT COUNT(*) FROM customer_private_messages WHERE customer_id=? AND sender_type='ADMIN' AND is_read=0"); $q->execute([$customerId]); return (int)$q->fetchColumn(); } catch(Throwable $e){ return 0; }
}
function admin_private_unread_count(): int {
    global $pdo; try { $q=$pdo->query("SELECT COUNT(*) FROM customer_private_messages WHERE sender_type='CUSTOMER' AND is_read=0"); return (int)$q->fetchColumn(); } catch(Throwable $e){ return 0; }
}
function process_cart_reminders(): void {
    global $pdo;
    if (!customer_logged_in()) return;
    $cid=(int)$_SESSION['customer_id']; $cart=$_SESSION['cart']??[]; if(!$cart) return;
    try {
        $pq=$pdo->prepare('SELECT email_order_updates,email_cart_reminders FROM customer_preferences WHERE customer_id=?');
        $pq->execute([$cid]); $pref=$pq->fetch();
        if ($pref && (int)$pref['email_cart_reminders']!==1) return;
        $cq=$pdo->prepare('SELECT full_name,email FROM customers WHERE customer_id=? AND status="ACTIVE" LIMIT 1');$cq->execute([$cid]);$c=$cq->fetch(); if(!$c) return;
        foreach($cart as $pid=>$qty){
            $pid=(int)$pid; $added=(int)($_SESSION['cart_added_at'][$pid]??0);
            if($added<=0){$_SESSION['cart_added_at'][$pid]=time(); continue;}
            if(time()-$added<86400) continue;
            $q=$pdo->prepare('SELECT product_id,product_name,quantity_in_stock,status,unit_price FROM products WHERE product_id=? LIMIT 1');$q->execute([$pid]);$prod=$q->fetch(); if(!$prod) continue;
            $rq=$pdo->prepare('SELECT reminder_id FROM cart_reminders WHERE customer_id=? AND product_id=? LIMIT 1');$rq->execute([$cid,$pid]); if($rq->fetch()) continue;
            $subject='Your Shadril cart is waiting — stock can change';
            $body='<p>Hello <strong>'.e($c['full_name']).'</strong>,</p><p><strong>'.e($prod['product_name']).'</strong> is still in your cart. Stock can change while an item is reserved in a cart, so complete your order while it is available.</p><p><strong>Current stock:</strong> '.(int)$prod['quantity_in_stock'].'</p>';
            $url='https://shadrackinventory.infinityfree.me/shop/cart.php';
            $sent=send_store_email((string)$c['email'],$subject,store_email_template('CART REMINDER','Your cart is waiting',$body,'Review my cart',$url));
            if($sent){
                try{$pdo->prepare('INSERT INTO cart_reminders(customer_id,product_id,reminded_at) VALUES(?,?,NOW())')->execute([$cid,$pid]);}catch(Throwable $ignore){}
                try{$pdo->prepare('INSERT INTO customer_messages(customer_id,subject,message) VALUES(?,?,?)')->execute([$cid,'Cart reminder','The item "'.(string)$prod['product_name'].'" is still in your cart. Stock can change, so complete your order soon.']);}catch(Throwable $ignore){}
            }
        }
    } catch(Throwable $ignore) {}
}
function social_links(): array {
    global $pdo; $out=[]; try{$rows=$pdo->query('SELECT platform,url FROM store_social_links ORDER BY link_id')->fetchAll();foreach($rows as $r){if(trim((string)$r['url'])!=='')$out[strtolower((string)$r['platform'])]=(string)$r['url'];}}catch(Throwable $ignore){} return $out;
}
function audit_log(string $action,string $details=''): void { global $pdo; try{$uid=admin_logged_in()?(int)($_SESSION['admin_id']??0):null;$ip=$_SERVER['REMOTE_ADDR']??null;$q=$pdo->prepare('INSERT INTO admin_audit_log(user_id,action,details,ip_address) VALUES(?,?,?,?)');$q->execute([$uid?:null,$action,$details,$ip]);}catch(Throwable $ignore){} }
function health_log(string $action,string $result='INFO',string $details=''): void { global $pdo; try{$uid=admin_logged_in()?(int)($_SESSION['admin_id']??0):null;$q=$pdo->prepare('INSERT INTO system_health_log(user_id,action,result,details) VALUES(?,?,?,?)');$q->execute([$uid?:null,$action,$result,$details]);}catch(Throwable $ignore){}}
function security_event(string $type,string $severity='INFO',string $details=''): void { global $pdo; try{$q=$pdo->prepare('INSERT INTO security_events(event_type,severity,details,ip_address) VALUES(?,?,?,?)');$q->execute([$type,$severity,$details,$_SERVER['REMOTE_ADDR']??null]);}catch(Throwable $ignore){}}
function send_welcome_email(string $email,string $name): bool { return send_store_email($email,'Welcome to Shadril Shopping Store',store_email_template('WELCOME','Your account has been created successfully','<p>Hello <strong>'.e($name).'</strong>,</p><p>Your Shadril Shopping Store customer account has been created successfully.</p><p>You can now sign in, browse products, save items, place orders and track your deliveries from your account centre.</p>','Sign in to Shadril','https://shadrackinventory.infinityfree.me/auth/customer-login.php')); }
function send_management_recovery_email(string $email,string $username,string $url): bool { return send_store_email($email,'Management password recovery',store_email_template('MANAGEMENT SECURITY','Reset your management password','<p>Hello <strong>'.e($username).'</strong>,</p><p>A management password reset was requested for your account. The link is valid for 15 minutes and does not require an OTP.</p>','Reset management password',$url)); }
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
