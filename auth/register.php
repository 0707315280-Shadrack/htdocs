<?php
require_once __DIR__.'/../includes/bootstrap.php';
if(customer_logged_in()) redirect('/');
$title='Create Customer Account'; $errors=[]; $success=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf();
  $name=trim((string)($_POST['full_name']??'')); $email=strtolower(trim((string)($_POST['email']??'')));
  $phone=trim((string)($_POST['phone']??'')); $username=trim((string)($_POST['username']??''));
  $pass=(string)($_POST['password']??''); $confirm=(string)($_POST['confirm_password']??'');
  if($name===''||$email===''||$username===''||$pass==='') $errors[]='Please complete all required fields.';
  if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[]='Enter a valid email address.';
  if($username!==''&&!preg_match('/^[A-Za-z0-9._-]{3,50}$/',$username)) $errors[]='Username must be 3-50 characters.';
  if(strlen($pass)<8) $errors[]='Password must be at least 8 characters.';
  if($pass!==$confirm) $errors[]='Passwords do not match.';
  if(!$errors){$s=$pdo->prepare('SELECT customer_id FROM customers WHERE LOWER(email)=LOWER(?) OR LOWER(username)=LOWER(?) LIMIT 1');$s->execute([$email,$username]);if($s->fetch())$errors[]='That email or username is already registered.';}
  if(!$errors){
    try{
      $hash=password_hash($pass,PASSWORD_DEFAULT);
      $s=$pdo->prepare("INSERT INTO customers(username,password_hash,full_name,email,phone,status,email_verified) VALUES(?,?,?,?,?,'ACTIVE',0)");
      $s->execute([$username,$hash,$name,$email,$phone]);
      $id=(int)$pdo->lastInsertId();
      $pdo->prepare('INSERT IGNORE INTO customer_preferences(customer_id) VALUES(?)')->execute([$id]);
      $mailSent=send_welcome_email($email,$name);
      $_SESSION['flash']=['type'=>'success','msg'=>$mailSent?'Account created successfully. A welcome email has been sent to your registered email.':'Account created successfully. Please sign in with your new account.'];
      redirect('/auth/customer-login.php');
    }catch(Throwable $e){$errors[]='Registration could not be completed. Please try again.';}
  }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?></title><link rel="icon" href="/assets/favicon.png"><link rel="stylesheet" href="/assets/style.css?v=22"></head><body class="auth-body"><div class="auth-wrap"><a class="brand auth-brand" href="/"><span class="brand-mark"><img src="/assets/shadril-logo.png" alt=""></span>Shadril Shopping Store</a><div class="auth-page-back"><?=back_button('Previous page')?></div><div class="auth-card"><div class="auth-copy"><span class="eyebrow">CUSTOMER ACCOUNT</span><h1>Create your account</h1><p>Register once and unlock secure shopping, order tracking, wishlist, reviews, support and notification controls.</p></div><?php if($errors):?><div class="errors"><?php foreach($errors as $x):?><div><?=e($x)?></div><?php endforeach;?></div><?php endif;?><form method="post" class="form" autocomplete="on"><?=csrf_field()?><label>Full name<input name="full_name" required value="<?=e($_POST['full_name']??'')?>" autocomplete="name"></label><div class="two"><label>Username<input name="username" required minlength="3" maxlength="50" value="<?=e($_POST['username']??'')?>" autocomplete="username"></label><label>Phone<input name="phone" value="<?=e($_POST['phone']??'')?>" autocomplete="tel"></label></div><label>Email<input type="email" name="email" required value="<?=e($_POST['email']??'')?>" autocomplete="email"></label><div class="two"><label>Password<input type="password" name="password" required minlength="8" autocomplete="new-password"></label><label>Confirm password<input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></label></div><button class="btn primary full" type="submit">Create account</button></form><div class="auth-divider"><span>OR</span></div><a class="google-btn" href="/auth/google-start.php">G&nbsp;&nbsp; Continue with Google</a><a class="btn home-return full" href="/">Return to homepage</a><p class="auth-links">Already registered? <a href="/auth/customer-login.php">Sign in</a> · <a href="/auth/forgot-password.php">Forgot password?</a></p><a class="management" href="/auth/login.php">Management access</a></div></div></body></html>
