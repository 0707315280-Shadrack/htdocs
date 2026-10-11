<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/site.php';

if (is_customer_logged_in() || is_admin_logged_in()) {
    redirect('/');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $email = trim((string)($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Enter a valid email address.';

    } else {

        $stmt = $pdo->prepare(
            'SELECT customer_id, full_name, email_verified, status
             FROM customers
             WHERE LOWER(email) = LOWER(?)
             LIMIT 1'
        );

        $stmt->execute([$email]);

        $customer = $stmt->fetch();

        if (!$customer) {

            $error =
                'If an account exists for that email, follow-up instructions can be requested here.';

        } elseif ((int)$customer['email_verified'] === 1) {

            $message =
                'This email is already verified. You can sign in now.';

        } elseif ($customer['status'] !== 'ACTIVE') {

            $error =
                'This customer account is not currently active. Please contact the store administrator.';

        } else {

            /*
             * Generate a fresh verification token.
             */
            $token = bin2hex(random_bytes(32));

            $stmt = $pdo->prepare(
                'UPDATE customers
                 SET verification_token = ?,
                     verification_expires_at =
                         DATE_ADD(NOW(), INTERVAL 24 HOUR)
                 WHERE customer_id = ?'
            );

            $stmt->execute([
                $token,
                (int)$customer['customer_id']
            ]);

            /*
             * Build the verification link.
             */
            $link =
                rtrim(SITE_URL, '/') .
                '/auth/verify-email.php?token=' .
                urlencode($token);

            /*
             * Send the verification email.
             */
            $headers =
                "From: Shadril Shopping Store <no-reply@shadrackinventory.infinityfree.me>\r\n" .
                "Reply-To: " . CONTACT_EMAIL . "\r\n" .
                "Content-Type: text/plain; charset=UTF-8";

            $subject = 'Verify your Shadril Shopping Store account';

            $body =
                "Hello {$customer['full_name']},\n\n" .
                "Please verify your Shadril Shopping Store account by opening the link below:\n\n" .
                "{$link}\n\n" .
                "This verification link expires in 24 hours.\n\n" .
                "Shadril Shopping Store";

            $sent = @mail(
                $email,
                $subject,
                $body,
                $headers
            );

            if ($sent) {

                $message =
                    'A new verification email has been sent. Please check your inbox and spam folder.';

            } else {

                $message =
                    'Your verification request was prepared, but the current hosting mail service did not send the email. Configure production email delivery on your hosting account.';
            }
        }
    }
}

$title = 'Resend Email Verification';

require __DIR__ . '/../includes/header.php';
?>

<section class="auth-shell">

    <div class="auth-card">

        <span class="eyebrow">
            ACCOUNT VERIFICATION
        </span>

        <h1>
            Verify your email
        </h1>

        <p>
            Enter the email you used during registration and
            we will issue a fresh verification link.
        </p>

        <?php if ($message): ?>

            <div class="alert success">
                <?= e($message) ?>
            </div>

        <?php endif; ?>

        <?php if ($error): ?>

            <div class="alert danger">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <form
            method="post"
            class="form-grid"
            autocomplete="on"
        >

            <?= csrf_field() ?>

            <label>
                Email address

                <input
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                >
            </label>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Send verification link
            </button>

        </form>

        <p class="center-text">

            <a href="/auth/customer-login.php">
                Back to sign in
            </a>

        </p>

    </div>

</section>

<?php
require __DIR__ . '/../includes/footer.php';
?>