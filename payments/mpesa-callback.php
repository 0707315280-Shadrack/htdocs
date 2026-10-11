<?php
declare(strict_types=1);

/*
 * Safaricom Daraja STK callback.
 * Public endpoint: no customer session required.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/mpesa.php';

header('Content-Type: application/json; charset=utf-8');

try {
    mpesa_ensure_schema();

    // Create admin payment notifications table if necessary.
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_payment_notifications (
                notification_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                event_type VARCHAR(30) NOT NULL,
                message VARCHAR(500) NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_payment_notice_read (is_read, created_at),
                INDEX idx_payment_notice_order (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Throwable $ignore) {
        error_log('Could not ensure admin payment notification table.');
    }

    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);

    $body = is_array($data)
        ? ($data['Body']['stkCallback'] ?? null)
        : null;

    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode([
            'ResultCode' => 1,
            'ResultDesc' => 'Invalid callback payload'
        ]);
        exit;
    }

    $checkout = trim((string)($body['CheckoutRequestID'] ?? ''));

    if ($checkout === '') {
        http_response_code(400);
        echo json_encode([
            'ResultCode' => 1,
            'ResultDesc' => 'Missing CheckoutRequestID'
        ]);
        exit;
    }

    // Use the existing database column names.
    $q = $pdo->prepare("
        SELECT
            mp.*,
            o.order_number,
            o.total_amount,
            o.status AS order_status,
            c.full_name,
            c.email
        FROM mpesa_payments mp
        JOIN orders o ON o.order_id = mp.order_id
        JOIN customers c ON c.customer_id = mp.customer_id
        WHERE mp.checkout_request_id = ?
        LIMIT 1
    ");
    $q->execute([$checkout]);
    $payment = $q->fetch(PDO::FETCH_ASSOC);

    // Acknowledge unknown callbacks without changing an order.
    if (!$payment) {
        echo json_encode([
            'ResultCode' => 0,
            'ResultDesc' => 'Callback acknowledged'
        ]);
        exit;
    }

    $resultCode = (string)($body['ResultCode'] ?? '');
    $resultDesc = substr(
        (string)($body['ResultDesc'] ?? ''),
        0,
        255
    );

    $receipt = '';
    $paidAmount = null;
    $paidPhone = '';

    $items = $body['CallbackMetadata']['Item'] ?? [];

    if (is_array($items)) {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = (string)($item['Name'] ?? '');
            $value = $item['Value'] ?? null;

            if ($name === 'MpesaReceiptNumber') {
                $receipt = trim((string)$value);
            } elseif ($name === 'Amount' && is_numeric($value)) {
                $paidAmount = (float)$value;
            } elseif ($name === 'PhoneNumber') {
                $paidPhone = trim((string)$value);
            }
        }
    }

    /*
     * Distinguish a confirmed success from an uncertain result.
     * A ResultCode of 0 without valid receipt/amount metadata must
     * NOT become FAILED, because that could allow a duplicate charge.
     */
    $amountMatches = (
        $paidAmount !== null
        && abs($paidAmount - round((float)$payment['amount'])) < 0.01
    );

    $success = (
        $resultCode === '0'
        && $receipt !== ''
        && $amountMatches
    );

    $uncertainSuccess = (
        $resultCode === '0'
        && !$success
    );

    if ($success) {
        $newStatus = 'SUCCESS';
    } elseif ($uncertainSuccess) {
        $newStatus = 'PENDING';

        $resultDesc = $receipt === ''
            ? 'Daraja reported success, but receipt metadata is missing. Manual reconciliation required.'
            : 'Daraja reported success, but the amount did not match the stored payment. Manual reconciliation required.';
    } elseif ($resultCode === '1032') {
        $newStatus = 'CANCELLED';
    } else {
        $newStatus = 'FAILED';
    }

    $notify = false;
    $orderConfirmed = false;
    $oldStatus = '';

    $pdo->beginTransaction();

    try {
        // Lock the payment row and recheck its state.
        $lock = $pdo->prepare("
            SELECT status
            FROM mpesa_payments
            WHERE id = ?
            FOR UPDATE
        ");
        $lock->execute([(int)$payment['id']]);
        $oldStatus = strtoupper((string)$lock->fetchColumn());

        /*
         * Do not downgrade an already successful payment if Daraja
         * sends a duplicate or delayed callback.
         */
        if ($oldStatus !== 'SUCCESS') {
            $update = $pdo->prepare("
                UPDATE mpesa_payments
                SET
                    result_code = ?,
                    result_description = ?,
                    mpesa_receipt = ?,
                    status = ?,
                    callback_payload = ?,
                    callback_received = 1
                WHERE id = ?
            ");

            $update->execute([
                $resultCode,
                mb_substr($resultDesc, 0, 255),
                $receipt !== '' ? $receipt : null,
                $newStatus,
                $raw,
                (int)$payment['id']
            ]);

            if ($success) {
                // Confirm only an order still awaiting confirmation.
                $orderUpdate = $pdo->prepare("
                    UPDATE orders
                    SET status = 'CONFIRMED'
                    WHERE order_id = ?
                      AND status = 'PENDING'
                ");
                $orderUpdate->execute([(int)$payment['order_id']]);

                $orderConfirmed = ($orderUpdate->rowCount() === 1);

                if ($orderConfirmed) {
                    $pdo->prepare("
                        INSERT INTO order_status_history
                            (order_id, status, notes)
                        VALUES (?, 'CONFIRMED', ?)
                    ")->execute([
                        (int)$payment['order_id'],
                        'M-Pesa payment verified. Receipt ' . $receipt
                    ]);
                }

                $adminMessage = 'M-Pesa payment verified for '
                    . $payment['order_number']
                    . ' · '
                    . money((float)$payment['amount'])
                    . ' · receipt '
                    . $receipt;

                if (!$orderConfirmed) {
                    $adminMessage .=
                        '. Order status was not PENDING; review required.';
                }

                try {
                    $pdo->prepare("
                        INSERT INTO admin_payment_notifications
                            (order_id, customer_id, event_type, message)
                        VALUES (?, ?, 'PAYMENT_SUCCESS', ?)
                    ")->execute([
                        (int)$payment['order_id'],
                        (int)$payment['customer_id'],
                        $adminMessage
                    ]);
                } catch (Throwable $ignore) {
                    error_log('Could not create M-Pesa success notification.');
                }

                $notify = true;

            } elseif (
                in_array($newStatus, ['FAILED', 'CANCELLED'], true)
                && in_array($oldStatus, ['INITIATED', 'PENDING'], true)
            ) {
                $note = $newStatus === 'CANCELLED'
                    ? 'Customer cancelled the M-Pesa prompt. Payment was not completed.'
                    : 'M-Pesa payment was not completed. Payment was not received.';

                try {
                    $pdo->prepare("
                        INSERT INTO customer_messages
                            (customer_id, subject, message)
                        VALUES (?, ?, ?)
                    ")->execute([
                        (int)$payment['customer_id'],
                        'Payment update · ' . $payment['order_number'],
                        $note . ' You may retry from the order page.'
                    ]);

                    $pdo->prepare("
                        INSERT INTO customer_private_messages
                            (customer_id, sender_type, sender_id, message, is_read)
                        VALUES (?, 'ADMIN', 0, ?, 0)
                    ")->execute([
                        (int)$payment['customer_id'],
                        'Payment update for '
                            . $payment['order_number'] . ': ' . $note
                    ]);
                } catch (Throwable $e) {
                    error_log('Could not save M-Pesa failure messages: '
                        . $e->getMessage());
                }

                try {
                    $pdo->prepare("
                        INSERT INTO admin_payment_notifications
                            (order_id, customer_id, event_type, message)
                        VALUES (?, ?, ?, ?)
                    ")->execute([
                        (int)$payment['order_id'],
                        (int)$payment['customer_id'],
                        $newStatus,
                        'Payment ' . $newStatus . ' for '
                            . $payment['order_number']
                            . ' · customer ' . $payment['full_name']
                    ]);
                } catch (Throwable $ignore) {
                    error_log('Could not create M-Pesa failure notification.');
                }

                $notify = true;
            } elseif ($uncertainSuccess) {
                // Keep the attempt PENDING to block further payment retries.
                try {
                    $pdo->prepare("
                        INSERT INTO admin_payment_notifications
                            (order_id, customer_id, event_type, message)
                        VALUES (?, ?, 'PAYMENT_REVIEW', ?)
                    ")->execute([
                        (int)$payment['order_id'],
                        (int)$payment['customer_id'],
                        'Manual review required for M-Pesa payment '
                            . $payment['order_number']
                            . '. Daraja reported success but receipt/amount validation failed.'
                    ]);
                } catch (Throwable $ignore) {
                    error_log('Could not create M-Pesa review notification.');
                }
            }
        }

        audit_log(
            'MPESA CALLBACK',
            $newStatus
                . ' order_id=' . (int)$payment['order_id']
                . ' result=' . $resultCode
        );

        $pdo->commit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    /*
     * Send success messages only after the database transaction commits.
     */
    if ($notify && $success) {
        $customerMessage = 'Payment received for order '
            . $payment['order_number']
            . '. Amount: ' . money((float)$payment['amount'])
            . '. M-Pesa receipt: ' . $receipt . '.';

        if ($orderConfirmed) {
            $customerMessage .=
                ' Your order is confirmed and tracking has been updated.';
        } else {
            $customerMessage .=
                ' Your payment is recorded. Please contact store support about the order status.';
        }

        try {
            $pdo->prepare("
                INSERT INTO customer_messages
                    (customer_id, subject, message)
                VALUES (?, ?, ?)
            ")->execute([
                (int)$payment['customer_id'],
                'M-Pesa payment confirmed · ' . $payment['order_number'],
                $customerMessage
            ]);

            $pdo->prepare("
                INSERT INTO customer_private_messages
                    (customer_id, sender_type, sender_id, message, is_read)
                VALUES (?, 'ADMIN', 0, ?, 0)
            ")->execute([
                (int)$payment['customer_id'],
                $customerMessage
            ]);
        } catch (Throwable $e) {
            error_log('Could not save payment success messages: '
                . $e->getMessage());
        }

        // Customer email.
        try {
            if (filter_var((string)$payment['email'], FILTER_VALIDATE_EMAIL)) {
                send_store_email(
                    (string)$payment['email'],
                    'Payment confirmed — ' . $payment['order_number'],
                    store_email_template(
                        'PAYMENT CONFIRMED',
                        'Your M-Pesa payment was received',
                        '<p>Hello ' . e((string)$payment['full_name']) . ',</p>'
                            . '<p>Your payment for order <strong>'
                            . e((string)$payment['order_number'])
                            . '</strong> has been verified.</p>'
                            . '<p><strong>Amount:</strong> '
                            . e(money((float)$payment['amount']))
                            . '<br><strong>M-Pesa receipt:</strong> '
                            . e($receipt) . '</p>'
                            . '<p>' . e($customerMessage) . '</p>',
                        'Track my order',
                        'https://shadrackinventory.infinityfree.me/customer/order.php?id='
                            . (int)$payment['order_id']
                    )
                );
            }
        } catch (Throwable $e) {
            error_log('M-Pesa customer email failed: ' . $e->getMessage());
        }

        // Administrator emails.
        try {
            $admins = $pdo->query("
                SELECT email, username
                FROM users
                WHERE role = 'Admin'
                  AND email IS NOT NULL
                  AND email <> ''
            ")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($admins as $admin) {
                if (!filter_var((string)$admin['email'], FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                send_store_email(
                    (string)$admin['email'],
                    'M-Pesa payment received — ' . $payment['order_number'],
                    store_email_template(
                        'PAYMENT ALERT',
                        'Customer payment verified',
                        '<p><strong>Order:</strong> '
                            . e((string)$payment['order_number'])
                            . '<br><strong>Customer:</strong> '
                            . e((string)$payment['full_name'])
                            . '<br><strong>Phone:</strong> '
                            . e((string)($paidPhone ?: $payment['phone']))
                            . '<br><strong>Amount:</strong> '
                            . e(money((float)$payment['amount']))
                            . '<br><strong>Receipt:</strong> '
                            . e($receipt)
                            . '</p><p>' . e($adminMessage ?? $customerMessage) . '</p>'
                    )
                );
            }
        } catch (Throwable $e) {
            error_log('M-Pesa administrator email failed: '
                . $e->getMessage());
        }

    } elseif ($notify && !$success) {
        // Notify the customer about a confirmed cancellation/failure.
        try {
            if (filter_var((string)$payment['email'], FILTER_VALIDATE_EMAIL)) {
                send_store_email(
                    (string)$payment['email'],
                    'M-Pesa payment not completed — ' . $payment['order_number'],
                    store_email_template(
                        'PAYMENT NOT COMPLETED',
                        'Your payment has not been received',
                        '<p>Your M-Pesa payment for order <strong>'
                            . e((string)$payment['order_number'])
                            . '</strong> was not completed.</p>'
                            . '<p>Please revisit your order page or contact store support if you need help.</p>',
                        'View my order',
                        'https://shadrackinventory.infinityfree.me/customer/order.php?id='
                            . (int)$payment['order_id']
                    )
                );
            }
        } catch (Throwable $e) {
            error_log('M-Pesa failure email failed: ' . $e->getMessage());
        }
    }

    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Callback processed'
    ]);

} catch (Throwable $e) {
    error_log('M-Pesa callback processing failed: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'ResultCode' => 1,
        'ResultDesc' => 'Callback processing failed'
    ]);
}
