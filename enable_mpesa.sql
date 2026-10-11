-- Shadril Shopping Store: enable the existing M-Pesa feature flag
-- Run ONLY after confirming the Daraja credentials and shortcode are for SANDBOX.
-- This changes one store_config setting; it does not modify orders or payment records.
INSERT INTO store_config (config_key, config_value)
VALUES ('mpesa_enabled', '1')
ON DUPLICATE KEY UPDATE config_value = '1';

SELECT config_key, config_value
FROM store_config
WHERE config_key = 'mpesa_enabled';
