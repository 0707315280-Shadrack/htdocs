<?php
declare(strict_types=1);


require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/mpesa.php';

require_admin();
mpesa_ensure_schema();

$title = 'M-Pesa & Pochi Payments';
$msg = '';
$err = '';


/*
 * ==============================================================
 * PAYMENT CONFIGURATION HELPER
 * ==============================================================
 */

function save_payment_config(
    string $key,
    string $value
): void {
    global $pdo;

    $q = $pdo->prepare(
        'INSERT INTO store_config(config_key, config_value)
         VALUES(?, ?)
         ON DUPLICATE KEY UPDATE
         config_value = VALUES(config_value)'
    );

    $q->execute([
        $key,
        $value
    ]);
}


/*
 * ==============================================================
 * ENCRYPTED M-PESA SECRET
 * ==============================================================
 */

function save_mpesa_secret(
    string $name,
    string $value
): void {

    if ($value === '') {
        return;
    }

    save_payment_config(
        'mpesa_' . $name . '_enc',
        mpesa_encrypt($value)
    );
}


/*
 * ==============================================================
 * POST ACTIONS
 * ==============================================================
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    check_csrf();

    if (!super_admin()) {

        http_response_code(403);

        exit(
            'Super Admin access is required.'
        );
    }

    try {

        $action = (string)(
            $_POST['action'] ?? ''
        );


        /*
         * ========================================================
         * SAVE M-PESA / DARAJA
         * ========================================================
         */

        if ($action === 'save_mpesa') {

           
            save_mpesa_secret(
                'consumer_key',
                trim(
                    (string)(
                        $_POST['consumer_key'] ?? ''
                    )
                )
            );


          

            save_mpesa_secret(
                'consumer_secret',
                trim(
                    (string)(
                        $_POST['consumer_secret'] ?? ''
                    )
                )
            );


           

            save_mpesa_secret(
                'passkey',
                trim(
                    (string)(
                        $_POST['passkey'] ?? ''
                    )
                )
            );


            

            $environment =
                (
                    $_POST['environment']
                    ?? 'sandbox'
                ) === 'production'
                    ? 'production'
                    : 'sandbox';


            /*
             * ----------------------------------------------------
             * TRANSACTION TYPE
             * ----------------------------------------------------
             */

            $transaction_type =
                (
                    $_POST['transaction_type']
                    ?? 'CustomerPayBillOnline'
                ) === 'CustomerBuyGoodsOnline'
                    ? 'CustomerBuyGoodsOnline'
                    : 'CustomerPayBillOnline';


            

            $shortcode = trim(
                (string)(
                    $_POST['shortcode'] ?? ''
                )
            );


            /*
             * ----------------------------------------------------
             * ACCOUNT REFERENCE
             * ----------------------------------------------------
             */

            $account_prefix = trim(
                (string)(
                    $_POST['account_prefix']
                    ?? 'SHADRIL'
                )
            );


            /*
             * ----------------------------------------------------
             * SAVE M-PESA SETTINGS
             * ----------------------------------------------------
             */

            save_payment_config(
                'mpesa_enabled',
                isset($_POST['enabled'])
                    ? '1'
                    : '0'
            );

            save_payment_config(
                'mpesa_environment',
                $environment
            );

            save_payment_config(
                'mpesa_shortcode',
                $shortcode
            );

            save_payment_config(
                'mpesa_transaction_type',
                $transaction_type
            );

            save_payment_config(
                'mpesa_account_prefix',
                $account_prefix
            );


            audit_log(
                'M-PESA SETTINGS UPDATED',
                'Daraja M-Pesa configuration updated by Super Admin. API secrets remain encrypted.'
            );

            $msg =
                'M-Pesa Daraja configuration saved securely.';
        }


        /*
         * ========================================================
         * SAVE POCHI LA BIASHARA
         * ========================================================
         */

        elseif ($action === 'save_pochi') {

            /*
             * Pochi is kept separate from Daraja API credentials.
             *
             * There is NO:
             * - Consumer Key
             * - Consumer Secret
             * - STK Passkey
             * - Daraja login password
             * - M-Pesa PIN
             *
             * in this section.
             */

            $pochi_enabled =
                isset($_POST['pochi_enabled'])
                    ? '1'
                    : '0';


            /*
             * ----------------------------------------------------
             * POCHI NAME
             * ----------------------------------------------------
             */

            $pochi_name = trim(
                (string)(
                    $_POST['pochi_name']
                    ?? 'Pochi la Biashara'
                )
            );


            /*
             * ----------------------------------------------------
             * POCHI BUSINESS NUMBER
             *
             * ⚠️ CHANGE THIS TO YOUR REAL POCHI NUMBER.
             *
             * FAKE EXAMPLE:
             * 0700000000
             * ----------------------------------------------------
             */

            $pochi_number = trim(
                (string)(
                    $_POST['pochi_number']
                    ?? ''
                )
            );


            /*
             * ----------------------------------------------------
             * POCHI PAYMENT INSTRUCTIONS
             * ----------------------------------------------------
             */

            $pochi_instructions = trim(
                (string)(
                    $_POST['pochi_instructions']
                    ?? ''
                )
            );


            save_payment_config(
                'pochi_enabled',
                $pochi_enabled
            );

            save_payment_config(
                'pochi_name',
                $pochi_name
            );

            save_payment_config(
                'pochi_number',
                $pochi_number
            );

            save_payment_config(
                'pochi_instructions',
                $pochi_instructions
            );


            audit_log(
                'POCHI SETTINGS UPDATED',
                'Pochi la Biashara payment settings updated by Super Admin.'
            );

            $msg =
                'Pochi la Biashara settings saved.';
        }


        /*
         * ========================================================
         * SAVE PAY ON DELIVERY
         * ========================================================
         */

        elseif ($action === 'save_cod') {

            $cod_enabled =
                isset($_POST['cod_enabled'])
                    ? '1'
                    : '0';


            save_payment_config(
                'cod_enabled',
                $cod_enabled
            );


            audit_log(
                'PAY ON DELIVERY SETTINGS UPDATED',
                'Pay on Delivery payment option updated by Super Admin.'
            );

            $msg =
                'Pay on Delivery settings saved.';
        }


        /*
         * ========================================================
         * DISABLE M-PESA
         * ========================================================
         */

        elseif ($action === 'disable_mpesa') {

            save_payment_config(
                'mpesa_enabled',
                '0'
            );

            audit_log(
                'M-PESA DISABLED',
                'M-Pesa online payment was disabled.'
            );

            $msg =
                'M-Pesa online payment has been disabled.';
        }


        /*
         * ========================================================
         * DISABLE POCHI
         * ========================================================
         */

        elseif ($action === 'disable_pochi') {

            save_payment_config(
                'pochi_enabled',
                '0'
            );

            audit_log(
                'POCHI DISABLED',
                'Pochi la Biashara payment option was disabled.'
            );

            $msg =
                'Pochi la Biashara has been disabled.';
        }


        /*
         * ========================================================
         * DISABLE PAY ON DELIVERY
         * ========================================================
         */

        elseif ($action === 'disable_cod') {

            save_payment_config(
                'cod_enabled',
                '0'
            );

            audit_log(
                'PAY ON DELIVERY DISABLED',
                'Pay on Delivery was disabled.'
            );

            $msg =
                'Pay on Delivery has been disabled.';
        }

    } catch (Throwable $e) {

        /*
         * Do not expose database credentials or
         * infrastructure details.
         */

        $err =
            'Payment settings could not be saved safely.';
    }
}


/*
 * ==============================================================
 * CURRENT M-PESA CONFIGURATION
 * ==============================================================
 */

$enabled = mpesa_enabled();

$env = mpesa_cfg(
    'environment',
    'sandbox'
);

$shortcode = mpesa_cfg(
    'shortcode',
    ''
);

$type = mpesa_cfg(
    'transaction_type',
    'CustomerPayBillOnline'
);

$account_prefix = mpesa_cfg(
    'account_prefix',
    'SHADRIL'
);


/*
 * ==============================================================
 * CURRENT POCHI CONFIGURATION
 * ==============================================================
 */

$pochi_enabled =
    store_config_value(
        'pochi_enabled',
        '0'
    ) === '1';

$pochi_name =
    store_config_value(
        'pochi_name',
        'Pochi la Biashara'
    );

$pochi_number =
    store_config_value(
        'pochi_number',
        ''
    );

$pochi_instructions =
    store_config_value(
        'pochi_instructions',
        'Pay using Pochi la Biashara and provide the payment reference for verification.'
    );


/*
 * ==============================================================
 * PAY ON DELIVERY
 * ==============================================================
 */

$cod_enabled =
    store_config_value(
        'cod_enabled',
        '1'
    ) === '1';


/*
 * ==============================================================
 * DARAJA CONFIGURATION STATUS
 * ==============================================================
 */

$configured =
    mpesa_secret('consumer_key') !== ''
    &&
    mpesa_secret('consumer_secret') !== ''
    &&
    mpesa_secret('passkey') !== ''
    &&
    $shortcode !== '';


/*
 * ==============================================================
 * CALLBACK URL
 *
 * Current installation callback.
 *
 * ⚠️ FOR PRODUCTION:
 * Use the neutral callback endpoint recommended for
 * your final Safaricom production configuration.
 * ==============================================================
 */

$callback =
    rtrim(
        store_config_value(
            'store_base_url',
            'https://shadrackinventory.infinityfree.me'
        ),
        '/'
    )
    . '/payments/mpesa-callback.php';


require __DIR__ . '/../includes/header.php';
?>


<div class="admin-layout">


    <!-- ==========================================================
         ADMIN SIDE NAVIGATION
    =========================================================== -->

    <aside class="admin-side">

        <strong>
            Management
        </strong>

        <a href="/admin/dashboard.php">
            Dashboard
        </a>

        <a href="/admin/analytics.php">
            Analytics &amp; Reports
        </a>

        <a href="/admin/products.php">
            Products
        </a>

        <a href="/admin/categories.php">
            Categories
        </a>

        <a href="/admin/stock.php">
            Stock control
        </a>

        <a href="/admin/orders.php">
            Orders
        </a>

        <a href="/admin/customers.php">
            Customers
        </a>

        <a href="/admin/reviews.php">
            Reviews
        </a>

        <a href="/admin/support.php">
            Support
        </a>

        <a
            class="active"
            href="/admin/mpesa.php"
        >
            Payments
        </a>

        <a href="/admin/database-security.php">
            Database security
        </a>

        <a href="/admin/audit.php">
            Audit log
        </a>

        <a href="/admin/system.php">
            System Health & Tests
        </a>

        <a href="/admin/settings.php">
            Settings
        </a>

    </aside>


    <section class="admin-main">


        <!-- ======================================================
             PAGE HEADER
        ======================================================= -->

        <div class="section-head">

            <div>

                <span class="eyebrow">
                    SECURE PAYMENT CONTROL
                </span>

                <h1>
                    M-Pesa, Pochi & Payment Methods
                </h1>

                <p>
                    Manage Daraja STK Push, Pochi la Biashara
                    and Pay on Delivery.
                </p>

            </div>

        </div>


        <!-- ======================================================
             SUCCESS MESSAGE
        ======================================================= -->

        <?php if ($msg): ?>

            <div class="flash success">
                <?= e($msg) ?>
            </div>

        <?php endif; ?>


        <!-- ======================================================
             ERROR MESSAGE
        ======================================================= -->

        <?php if ($err): ?>

            <div class="errors">
                <?= e($err) ?>
            </div>

        <?php endif; ?>


        <!-- ======================================================
             M-PESA STATUS
        ======================================================= -->

        <div class="panel">

            <h2>
                M-Pesa / Safaricom Daraja
            </h2>

            <p>

                <strong>

                    <?= $enabled
                        ? 'ONLINE PAYMENT ENABLED'
                        : 'ONLINE PAYMENT DISABLED'
                    ?>

                </strong>

                ·

                <?= $configured
                    ? 'Credentials configured'
                    : 'Credentials not configured'
                ?>

                · Environment:

                <strong>
                    <?= e(
                        strtoupper($env)
                    ) ?>
                </strong>

            </p>


            <p class="muted">

                Customer STK callback:

                <code>
                    <?= e($callback) ?>
                </code>

            </p>

        </div>


        <?php if (super_admin()): ?>


        <!-- ======================================================
             DARAJA STK PUSH FORM
        ======================================================= -->

        <form
            method="post"
            class="form panel"
        >

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="action"
                value="save_mpesa"
            >


            <h2>
                Daraja STK Push Configuration
            </h2>


            <p class="muted">

                Start with Sandbox. Use Production only after
                Safaricom has provisioned the required live
                business setup and credentials.

            </p>


            <!-- ==================================================
                 ENABLE M-PESA
            =================================================== -->

            <label>

                <input
                    type="checkbox"
                    name="enabled"
                    <?= $enabled
                        ? 'checked'
                        : ''
                    ?>
                >

                Enable M-Pesa online payment

            </label>


            <!-- ==================================================
                 ENVIRONMENT
            =================================================== -->

            <label>

                Environment

                <select
                    name="environment"
                >

                    <option
                        value="sandbox"
                        <?= $env === 'sandbox'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Sandbox
                    </option>

                    <option
                        value="production"
                        <?= $env === 'production'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Production
                    </option>

                </select>

            </label>


            <!-- ==================================================
                 BUSINESS SHORT CODE
            =================================================== -->

            <label>

                Business Short Code

               

                <input
                    name="shortcode"
                    value="<?= e($shortcode) ?>"
                    autocomplete="off"
                    placeholder="Enter your Daraja shortcode"
                >

            </label>


            <!-- ==================================================
                 TRANSACTION TYPE
            =================================================== -->

            <label>

                Transaction Type

                <select
                    name="transaction_type"
                >

                    <option
                        value="CustomerPayBillOnline"
                        <?= $type === 'CustomerPayBillOnline'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        PayBill — STK Push
                    </option>

                    <option
                        value="CustomerBuyGoodsOnline"
                        <?= $type === 'CustomerBuyGoodsOnline'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Buy Goods / Till — STK Push
                    </option>

                </select>

            </label>


            <!-- ==================================================
                 CONSUMER KEY
            =================================================== -->

            <label>

                Consumer Key

              
                <input
                    name="consumer_key"
                    type="password"
                    autocomplete="new-password"
                    placeholder="CHANGE THIS IN ADMIN"
                >

            </label>


            <!-- ==================================================
                 CONSUMER SECRET
            =================================================== -->

            <label>

                Consumer Secret

               

                <input
                    name="consumer_secret"
                    type="password"
                    autocomplete="new-password"
                    placeholder="CHANGE THIS IN ADMIN"
                >

            </label>


            <!-- ==================================================
                 STK PASSKEY
            =================================================== -->

            <label>

                STK Passkey

                

                <input
                    name="passkey"
                    type="password"
                    autocomplete="new-password"
                    placeholder="CHANGE THIS IN ADMIN"
                >

            </label>


            <!-- ==================================================
                 ACCOUNT REFERENCE
            =================================================== -->

            <label>

                Account Reference Prefix

                <input
                    name="account_prefix"
                    value="<?= e($account_prefix) ?>"
                >

            </label>


            <button
                type="submit"
                class="btn primary"
            >
                Save M-Pesa Securely
            </button>

        </form>


        <!-- ======================================================
             POCHI LA BIASHARA
        ======================================================= -->

        <form
            method="post"
            class="form panel"
        >

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="action"
                value="save_pochi"
            >


            <h2>
                Pochi la Biashara
            </h2>


            <p class="muted">

                
            </p>


            <!-- ==================================================
                 ENABLE POCHI
            =================================================== -->

            <label>

                <input
                    type="checkbox"
                    name="pochi_enabled"
                    <?= $pochi_enabled
                        ? 'checked'
                        : ''
                    ?>
                >

                Enable Pochi payment option

            </label>


            <!-- ==================================================
                 POCHI NAME
            =================================================== -->

            <label>

                Payment Name

                <input
                    name="pochi_name"
                    value="<?= e($pochi_name) ?>"
                >

            </label>


            <!-- ==================================================
                 POCHI NUMBER
            =================================================== -->

            <label>

                Pochi Business Number

                

                <input
                    name="pochi_number"
                    value="<?= e($pochi_number) ?>"
                    autocomplete="off"
                    placeholder="Enter your Pochi business number"
                >

            </label>


            <!-- ==================================================
                 POCHI INSTRUCTIONS
            =================================================== -->

            <label>

                Customer Payment Instructions

                <textarea
                    name="pochi_instructions"
                    rows="5"
                    placeholder="Example: Pay using Pochi and provide your payment reference."
                ><?= e($pochi_instructions) ?></textarea>

            </label>


            <button
                type="submit"
                class="btn primary"
            >
                Save Pochi Settings
            </button>

        </form>


        <!-- ======================================================
             PAY ON DELIVERY
        ======================================================= -->

        <form
            method="post"
            class="form panel"
        >

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="action"
                value="save_cod"
            >


            <h2>
                Pay on Delivery
            </h2>


            <p class="muted">

                Allow customers to place an order and pay when
                the order is delivered.

            </p>


            <label>

                <input
                    type="checkbox"
                    name="cod_enabled"
                    <?= $cod_enabled
                        ? 'checked'
                        : ''
                    ?>
                >

                Enable Pay on Delivery

            </label>


            <button
                type="submit"
                class="btn primary"
            >
                Save Pay on Delivery
            </button>

        </form>


        <!-- ======================================================
             PAYMENT CONTROLS
        ======================================================= -->

        <div class="panel">

            <h2>
                Payment Controls
            </h2>


            <div
                style="
                    display:flex;
                    gap:10px;
                    flex-wrap:wrap;
                "
            >


                <!-- DISABLE M-PESA -->

                <form method="post">

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="disable_mpesa"
                    >

                    <button
                        type="submit"
                        class="btn"
                    >
                        Disable M-Pesa
                    </button>

                </form>


                <!-- DISABLE POCHI -->

                <form method="post">

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="disable_pochi"
                    >

                    <button
                        type="submit"
                        class="btn"
                    >
                        Disable Pochi
                    </button>

                </form>


                <!-- DISABLE PAY ON DELIVERY -->

                <form method="post">

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="disable_cod"
                    >

                    <button
                        type="submit"
                        class="btn"
                    >
                        Disable Pay on Delivery
                    </button>

                </form>

            </div>

        </div>


        <?php else: ?>


        <!-- ======================================================
             NON-SUPER ADMIN
        ======================================================= -->

        <div class="panel">

            <strong>
                Protected.
            </strong>

            <p>

               
            </p>

        </div>


        <?php endif; ?>


        <!-- ======================================================
             PAYMENT SECURITY
        ======================================================= -->

        <div class="panel">

            <h2>
                Payment Security
            </h2>


            <ul>

                <li>
                    
                </li>

                <li>
                   
                </li>

                <li>
                   
                </li>

                <li>
                    
                </li>

                <li>
                   
                </li>

                <li>
                   
                </li>

                <li>
                  
                </li>

                <li>
                    
                </li>

                <li>
                    
                </li>

                <li>
                   
                </li>

                <li>
                    
                </li>

            </ul>

        </div>


<div class="panel">
    <h2>Daraja Configuration Guidance</h2>

    <p>
        Use credentials issued for your own Daraja developer
        application. Do not copy example credentials from this page.
    </p>

    <ul>
        <li>
            <strong>Environment:</strong>
            Select Sandbox for sandbox testing or Production
            only when your live Daraja setup is approved.
        </li>
        <li>
            <strong>Business Short Code:</strong>
            Enter the shortcode associated with the selected
            Daraja environment and transaction type.
        </li>
        <li>
            <strong>Consumer Key and Consumer Secret:</strong>
            Obtain these from your Daraja application.
        </li>
        <li>
            <strong>STK Passkey:</strong>
            Use the passkey associated with the appropriate
            STK Push shortcode and environment.
        </li>
        <li>
            <strong>Pochi la Biashara:</strong>
            Configure this separately. A Pochi phone number
            is not automatically a Daraja STK Push shortcode.
        </li>
    </ul>

    <p class="muted">
        Keep credentials private. Leave secret fields blank
        when saving other settings if you want to preserve
        the credentials already stored.
    </p>
</div>

        <!-- ======================================================
             PAYMENT METHODS SUMMARY
        ======================================================= -->

        <div class="panel">

            <h2>
                Store Payment Methods
            </h2>


            <div class="table-responsive">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Method
                            </th>

                            <th>
                                Purpose
                            </th>

                            <th>
                                Confirmation
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <!-- M-PESA -->

                        <tr>

                            <td>
                                <strong>
                                    M-Pesa STK Push
                                </strong>
                            </td>

                            <td>
                                Customer receives an M-Pesa
                                payment prompt on their phone.
                            </td>

                            <td>
                                Automatic through the Daraja
                                payment callback.
                            </td>

                        </tr>


                        <!-- POCHI -->

                        <tr>

                            <td>
                                <strong>
                                    Pochi la Biashara
                                </strong>
                            </td>

                            <td>
                                Customer pays the merchant
                                through Pochi.
                            </td>

                            <td>
                                Manual verification unless an
                                official automated integration
                                is available.
                            </td>

                        </tr>


                        <!-- PAY DELIVERY -->

                        <tr>

                            <td>
                                <strong>
                                    Pay on Delivery
                                </strong>
                            </td>

                            <td>
                                Customer pays when the order
                                is delivered.
                            </td>

                            <td>
                                Confirmed through the delivery
                                process.
                            </td>

                        </tr>


                    </tbody>

                </table>

            </div>

        </div>


        <!-- ======================================================
             SYSTEM TESTS
        ======================================================= -->

        <div class="panel">

            <h2>
                Payment Testing
            </h2>

            <p>
                All payment integration checks should be performed
                from:
            </p>

            <p>
                <strong>
                    Admin → System Health & Tests
                </strong>
            </p>

            <p class="muted">

                The payment test centre should verify the M-Pesa
                database, PHP cURL, OpenSSL, Daraja configuration,
                callback handler, checkout connection and Daraja
                authentication without making an unintended real
                customer charge.

            </p>

            <a
                href="/admin/system.php"
                class="btn primary"
            >
                Open System Health & Tests
            </a>

        </div>


    </section>

</div>


<?php
require __DIR__ . '/../includes/footer.php';
?>