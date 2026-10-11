<?php
declare(strict_types=1);
require_once __DIR__.'/site.php';
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function money(float|int|string $amount): string { return 'KES ' . number_format((float)$amount, 2); }
function redirect(string $url): never { header('Location: '.$url); exit; }
function csrf_token(): string { if(empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(24)); return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void { if(!hash_equals((string)($_SESSION['_csrf']??''),(string)($_POST['_csrf']??''))){http_response_code(419);exit('Security check failed. Please go back and try again.');} }
function whatsapp_url(string $message=''): string { return 'https://wa.me/'.WHATSAPP_NUMBER.'?text='.rawurlencode($message); }
function product_image(?string $url, string $name=''): string { return trim((string)$url)!=='' ? $url : 'https://placehold.co/700x700/f7f7f7/1f2937?text='.rawurlencode($name ?: 'Product'); }
function slug_text(string $text): string { $text=preg_replace('/[^a-z0-9]+/i','-',strtolower(trim($text))); return trim((string)$text,'-'); }
function upload_product_image(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Image upload failed.');
    if (($file['size'] ?? 0) > 3 * 1024 * 1024) throw new RuntimeException('Product image must be 3MB or smaller.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) throw new RuntimeException('Use JPG, PNG or WEBP images only.');
    $dir = __DIR__.'/../assets/uploads/products';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Product image folder is not available.');
    $name = 'product-'.bin2hex(random_bytes(8)).'.'.$allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir.'/'.$name)) throw new RuntimeException('Could not save the product image.');
    return '/assets/uploads/products/'.$name;
}
