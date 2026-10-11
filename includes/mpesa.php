<?php
declare(strict_types=1);
require_once __DIR__.'/../config/mpesa.php';

function mpesa_cfg(string $key, string $default=''): string {
    return store_config_value('mpesa_'.$key, $default);
}
function mpesa_key(): string {
    $k = MPESA_ENCRYPTION_KEY;
    if (str_starts_with($k, 'REPLACE_WITH_')) throw new RuntimeException('M-Pesa encryption key is not configured.');
    return hash('sha256', $k, true);
}
function mpesa_encrypt(string $plain): string {
    $iv = random_bytes(12); $tag='';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', mpesa_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ct === false) throw new RuntimeException('Could not encrypt M-Pesa credential.');
    return base64_encode($iv.$tag.$ct);
}
function mpesa_decrypt(string $encoded): string {
    if ($encoded==='') return '';
    $raw=base64_decode($encoded,true); if($raw===false || strlen($raw)<29) return '';
    $iv=substr($raw,0,12);$tag=substr($raw,12,16);$ct=substr($raw,28);
    $plain=openssl_decrypt($ct,'aes-256-gcm',mpesa_key(),OPENSSL_RAW_DATA,$iv,$tag);
    return $plain===false?'':$plain;
}
function mpesa_secret(string $name): string { return mpesa_decrypt(mpesa_cfg($name.'_enc','')); }
function mpesa_enabled(): bool { return mpesa_cfg('enabled','0')==='1'; }
function mpesa_base_url(): string { return mpesa_cfg('environment','sandbox')==='production' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke'; }
function mpesa_phone(string $phone): string {
    $p=preg_replace('/\D+/','',$phone);
    if(str_starts_with($p,'07')||str_starts_with($p,'01')) $p='254'.substr($p,1);
    if(str_starts_with($p,'7')||str_starts_with($p,'1')) $p='254'.$p;
    if(!preg_match('/^254[17]\d{8}$/',$p)) throw new InvalidArgumentException('Enter a valid Kenyan M-Pesa number.');
    return $p;
}
function mpesa_access_token(): string {
    $key=mpesa_secret('consumer_key');$secret=mpesa_secret('consumer_secret');
    if($key===''||$secret==='') throw new RuntimeException('M-Pesa Consumer Key/Secret are not configured.');
    $url=mpesa_base_url().'/oauth/v1/generate?grant_type=client_credentials';
    $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_USERPWD=>$key.':'.$secret,CURLOPT_HTTPAUTH=>CURLAUTH_BASIC]);
    $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
    if($body===false||$code<200||$code>=300) throw new RuntimeException('M-Pesa authorization failed (HTTP '.$code.'). '.($err?:'Check Daraja credentials and environment.'));
    $json=json_decode($body,true);if(empty($json['access_token'])) throw new RuntimeException('M-Pesa authorization did not return an access token.');return (string)$json['access_token'];
}
function mpesa_stk_push(int $orderId,float $amount,string $phone,string $accountReference,string $description): array {
    $shortcode=mpesa_cfg('shortcode');$passkey=mpesa_secret('passkey');$type=mpesa_cfg('transaction_type','CustomerPayBillOnline');
    if($shortcode===''||$passkey==='') throw new RuntimeException('M-Pesa Short Code/Passkey are not configured.');
    $phone=mpesa_phone($phone);$timestamp=date('YmdHis');$password=base64_encode($shortcode.$passkey.$timestamp);
    $callback=rtrim((string)(store_config_value('store_base_url','https://shadrackinventory.infinityfree.me')),'/').'/payments/mpesa-callback.php';
    $payload=['BusinessShortCode'=>$shortcode,'Password'=>$password,'Timestamp'=>$timestamp,'TransactionType'=>$type,'Amount'=>max(1,(int)round($amount)),'PartyA'=>$phone,'PartyB'=>$shortcode,'PhoneNumber'=>$phone,'CallBackURL'=>$callback,'AccountReference'=>$accountReference,'TransactionDesc'=>substr($description,0,120)];
    $ch=curl_init(mpesa_base_url().'/mpesa/stkpush/v1/processrequest');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.mpesa_access_token()],CURLOPT_POSTFIELDS=>json_encode($payload)]);
    $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
    if($body===false) throw new RuntimeException('M-Pesa STK request failed: '.$err);
    $json=json_decode($body,true)?:[];
    if($code<200||$code>=300||empty($json['CheckoutRequestID'])) throw new RuntimeException('M-Pesa STK request was not accepted. '.((string)($json['errorMessage']??$json['ResponseDescription']??'HTTP '.$code)));
    return $json;
}

/**
 * Ensure the M-Pesa payment table exists without depending on the exact
 * integer type/foreign-key definition of an older live orders table.
 * This is important on shared hosting where an older schema may already
 * exist with a different key definition.
 */
function mpesa_ensure_schema(): void {
    global $pdo;
    static $done = false;

    if ($done) {
        return;
    }

    try {
        /*
         * This table may already exist with a different schema.
         * CREATE TABLE IF NOT EXISTS will not modify an existing table.
         * The live table uses id, result_description and callback_payload.
         * Do not drop or recreate the existing payment table.
         */
        $pdo->exec("CREATE TABLE IF NOT EXISTS mpesa_payments (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        order_id INT UNSIGNED NULL,
        customer_id INT UNSIGNED NULL,
        merchant_request_id VARCHAR(100) NULL,
        checkout_request_id VARCHAR(100) NULL,
        phone VARCHAR(20) NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        account_reference VARCHAR(100) NULL,
        transaction_description VARCHAR(255) NULL,
        transaction_type VARCHAR(50) NOT NULL DEFAULT 'CustomerPayBillOnline',
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        mpesa_receipt VARCHAR(100) NULL,
        result_code VARCHAR(20) NULL,
        result_description VARCHAR(255) NULL,
        callback_received TINYINT(1) NOT NULL DEFAULT 0,
        callback_payload LONGTEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_mpesa_order (order_id),
        INDEX idx_mpesa_customer (customer_id),
        INDEX idx_mpesa_checkout (checkout_request_id),
        INDEX idx_mpesa_merchant (merchant_request_id),
        INDEX idx_mpesa_receipt (mpesa_receipt),
        INDEX idx_mpesa_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $done = true;
    } catch (Throwable $e) {
        // Do not mark the schema check complete if MySQL rejected the statement.
        $done = false;
        throw $e;
    }
}


function mpesa_payment_for_order(int $orderId): ?array {
    global $pdo;

    try {
        mpesa_ensure_schema();

        $q = $pdo->prepare(
            'SELECT * FROM mpesa_payments
             WHERE order_id = ?
             ORDER BY id DESC
             LIMIT 1'
        );

        $q->execute([$orderId]);

        return $q->fetch() ?: null;

    } catch (Throwable $e) {
        error_log('M-Pesa payment lookup failed: ' . $e->getMessage());
        return null;
    }
}

