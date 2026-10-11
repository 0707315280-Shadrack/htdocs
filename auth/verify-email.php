<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

$token = trim((string)($_GET['token'] ?? ''));

$message = 'Verification link is invalid or expired.';

if ($token !== '') {

    /*
    |--------------------------------------------------------------------------
    | Find Valid Verification Token
    |--------------------------------------------------------------------------
    */
    $stmt = $pdo->prepare(
        'SELECT customer_id
         FROM customers
         WHERE verification_token = ?
           AND verification_expires_at > NOW()
         LIMIT 1'
    );

    $stmt->execute([$token]);

    $customer = $stmt->fetch();

    if ($customer) {

        /*
        |--------------------------------------------------------------------------
        | Verify Customer Email
        |--------------------------------------------------------------------------
        */
        $stmt = $pdo->prepare(
            'UPDATE customers
             SET email_verified = 1,
                 status = "ACTIVE",
                 verification_token = NULL,
                 verification_expires_at = NULL
             WHERE customer_id = ?'
        );

        $stmt->execute([
            (int)$customer['customer_id']
        ]);

        $message = 'Your email has been verified. You can now sign in.';
    }
}

$title = 'Email Verification';

require __DIR__ . '/../includes/header.php';
?>

<section class="auth-shell">

    <div class="auth-card center-card">

        <span
            class="success-icon"
            aria-hidden="true"
        >
            ✓
        </span>

        <h1>Email verification</h1>

        <p>
            <?= e($message) ?>
        </p>

        <a
            class="btn btn-primary"
            href="/auth/customer-login.php"
        >
            Continue to sign in
        </a>

    </div>

</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>