<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

if (is_admin_logged_in()) {
    redirect('/admin/dashboard.php');
}

if (is_customer_logged_in()) {
    redirect('/customer/profile.php');
}

$title = 'Account';

require __DIR__ . '/../includes/header.php';
?>

<section class="auth-shell">

    <div class="auth-card">

        <span class="eyebrow">
            YOUR ACCOUNT
        </span>

        <h1>
            Welcome to Shadril
        </h1>

        <p>
            Sign in to manage orders, save products and
            checkout faster. New here? Create a customer
            account in minutes.
        </p>

        <div class="auth-choice">

            <a
                class="btn btn-primary btn-wide"
                href="/auth/customer-login.php"
            >
                Sign in to your account
            </a>

            <a
                class="btn btn-outline btn-wide"
                href="/auth/register.php"
            >
                Create a customer account
            </a>

        </div>

        <p class="small-muted">
            Management access is protected and is available
            only to authorised Admin or Staff accounts.
        </p>

        <a
            class="btn btn-management btn-wide"
            href="/auth/login.php"
        >
            🔐 Authorised management access
        </a>

    </div>

</section>

<?php
require __DIR__ . '/../includes/footer.php';
?>