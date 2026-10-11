<?php
declare(strict_types=1);

/*
 * Shadril Shopping Store
 * Google OAuth configuration
 *
 * IMPORTANT:
 * - Keep the Client Secret private.
 * - Do not upload this file to a public GitHub repository.
 * - The Redirect URI must exactly match the URI
 *   registered in Google Cloud Console.
 */

const GOOGLE_CLIENT_ID = 'YOUR_GOOGLE_CLIENT_ID';

const GOOGLE_CLIENT_SECRET = 'YOUR_GOOGLE_CLIENT_SECRET';

const GOOGLE_REDIRECT_URI =
    'https://shadrackinventory.infinityfree.me/auth/google-callback.php';


function google_enabled(): bool
{
    return
        GOOGLE_CLIENT_ID !== '' &&
        GOOGLE_CLIENT_SECRET !== '' &&
        GOOGLE_CLIENT_ID !== 'YOUR_GOOGLE_CLIENT_ID' &&
        GOOGLE_CLIENT_SECRET !== 'YOUR_GOOGLE_CLIENT_SECRET';
}