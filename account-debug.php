<?php
// TEMPORARY diagnostic page. Delete after testing.
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);
register_shutdown_function(function(){
    $e=error_get_last();
    if($e && in_array($e['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)){
        http_response_code(500);
        echo '<h2>PHP fatal error detected</h2><pre>'.htmlspecialchars(print_r($e,true),ENT_QUOTES,'UTF-8').'</pre>';
    }
});
header('Content-Type: text/html; charset=UTF-8');
echo '<h1>Shadril Account Diagnostic</h1>';
echo '<p><b>PHP version:</b> '.htmlspecialchars(PHP_VERSION).'</p>';
echo '<p><b>Document root:</b> '.htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? '').'</p>';
echo '<p><b>Script:</b> '.htmlspecialchars(__FILE__).'</p>';
$bootstrap=__DIR__.'/includes/bootstrap.php';
echo '<p><b>Bootstrap exists:</b> '.(is_file($bootstrap)?'YES':'NO').'</p>';
try {
  require_once $bootstrap;
  echo '<p><b>Bootstrap:</b> loaded successfully</p>';
  echo '<p><b>PDO:</b> '.(isset($pdo) && $pdo instanceof PDO ? 'connected' : 'NOT CONNECTED').'</p>';
  if(isset($pdo) && $pdo instanceof PDO){
    $n=(int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    echo '<p><b>customers table:</b> OK ('.$n.' rows)</p>';
  }
  echo '<p><b>customer_logged_in:</b> '.(function_exists('customer_logged_in')?(customer_logged_in()?'YES':'NO'):'MISSING').'</p>';
  echo '<p><b>customer_row:</b> '.(function_exists('customer_row')?'AVAILABLE':'MISSING').'</p>';
  if(function_exists('customer_logged_in') && customer_logged_in() && function_exists('customer_row')){
    $c=customer_row();
    echo '<p><b>Current customer:</b> '.($c ? 'FOUND (ID '.(int)$c['customer_id'].')' : 'NOT FOUND').'</p>';
  }
} catch(Throwable $e){
  http_response_code(500);
  echo '<h2>Runtime error</h2><pre>'.htmlspecialchars($e->getMessage().'\n\n'.$e->getFile().':'.$e->getLine().'\n\n'.$e->getTraceAsString(),ENT_QUOTES,'UTF-8').'</pre>';
}
echo '<hr><p><b>Important:</b> This is temporary. Delete account-debug.php after testing.</p>';
