<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$raw=(string)($_GET['ids']??($_GET['id']??''));
$ids=[];foreach(preg_split('/[^0-9]+/',$raw,-1,PREG_SPLIT_NO_EMPTY) as $v){$n=(int)$v;if($n>0)$ids[]=$n;}
$ids=array_values(array_unique($ids));if(!$ids||count($ids)>60){http_response_code(400);echo json_encode(['ok'=>false,'message'=>'Provide 1-60 product ids.']);exit;}
$in=implode(',',array_fill(0,count($ids),'?'));
try{$q=$pdo->prepare("SELECT product_id,quantity_in_stock,status FROM products WHERE product_id IN ($in)");$q->execute($ids);$out=[];foreach($q->fetchAll() as $r){$qty=(int)$r['quantity_in_stock'];$out[(string)$r['product_id']]=['quantity'=>$qty,'available'=>strtoupper((string)$r['status'])==='ACTIVE'&&$qty>0];}echo json_encode(['ok'=>true,'products'=>$out],JSON_UNESCAPED_SLASHES);}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'message'=>'Unable to read live stock.']);}
