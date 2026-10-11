<?php
declare(strict_types=1);

/**
 * M-Pesa configuration
 *
 * This key is used by includes/mpesa.php to encrypt and decrypt
 * M-Pesa credentials saved by the admin settings page.
 *
 * IMPORTANT:
 * - Keep this file private.
 * - Do not change the key without carefully migrating stored credentials.
 * - admin/mpesa.php must use the same encryption key.
 */

if (!defined('MPESA_ENCRYPTION_KEY')) {
    define(
        'MPESA_ENCRYPTION_KEY',
        '7wwL6PFL5vAuRbzk4nSjr4N35b1AfZdy'
    );
}
