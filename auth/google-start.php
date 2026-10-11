<?php
require_once __DIR__.'/../includes/bootstrap.php';
$cfgFile=__DIR__.'/../config/google.php';
$cfg=is_file($cfgFile)?require $cfgFile:[];
$clientId=trim((string)($cfg['client_id']??''));
$clientSecret=trim((string)($cfg['client_secret']??''));
$redirectUri=trim((string)($cfg['redirect_uri']??'https://shadrackinventory.infinityfree.me/auth/google-callback.php'));
if($clientId==='' || $clientSecret==='' || str_contains($clientId,'YOUR_') || str_contains($clientSecret,'YOUR_')){
  $title='Google Sign-In Setup';
  ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?></title><link rel="stylesheet" href="/assets/style.css?v=22"><style><?=file_get_contents(__DIR__.'/../assets/style.css')?></style></head><body class="auth-body"><script>function shadrilGoBack(){try{if(document.referrer){var u=new URL(document.referrer,location.href);if(u.origin===location.origin){history.back();return false;}}}catch(e){}location.href="/";return false;}</script><div class="auth-wrap"><a class="brand auth-brand" href="/">Shadril Shopping Store</a><div class="auth-page-back"><?=back_button('Previous page')?></div><div class="auth-card auth-special"><div class="auth-icon">G</div><div class="auth-copy"><span class="eyebrow">SECURE GOOGLE SIGN-IN</span><h1>Google sign-in setup</h1><p>Google requires a Web OAuth client belonging to this store. The website is ready; one Google credential step remains before customers can use this option.</p></div><div class="panel" style="text-align:left;margin:18px 0"><strong>Use this exact redirect URI</strong><code style="display:block;word-break:break-all;margin-top:8px;padding:10px;background:#f7f7f7;border-radius:8px"><?=e($redirectUri)?></code><p style="font-size:12px;color:#666">Create a Google OAuth 2.0 Client ID for a Web application and add the URI above under Authorized redirect URIs.</p></div><a class="btn primary full" target="_blank" rel="noopener" href="https://console.cloud.google.com/apis/credentials">Open Google Cloud Credentials</a><p class="auth-links"><a href="/auth/register.php">← Back to registration</a></p><a class="management" href="/auth/login.php">Management access</a></div></div></body></html><?php exit;
}
$state=bin2hex(random_bytes(24));$_SESSION['google_state']=$state;$_SESSION['google_next']='/';
$params=['client_id'=>$clientId,'redirect_uri'=>$redirectUri,'response_type'=>'code','scope'=>'openid email profile','access_type'=>'online','include_granted_scopes'=>'true','state'=>$state,'prompt'=>'select_account'];
header('Location: https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query($params));exit;
