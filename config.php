<?php
/**
 * MTN MoMo API Configuration
 * DEEPNEXIS Ltd - Production
 */

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Environment settings
define('ENVIRONMENT', $_ENV['MOMO_ENVIRONMENT'] ?? 'sandbox');
define('TARGET_ENVIRONMENT', $_ENV['MOMO_TARGET_ENVIRONMENT'] ?? 'sandbox');
define('BASE_URL', $_ENV['MOMO_BASE_URL'] ?? 'https://proxy.momoapi.mtn.co.rw');
define('CURRENCY', 'RWF');

// Collection API Configuration
define('COLLECTION_CONFIG', [
    'subscription_key' => $_ENV['MOMO_COLLECTION_SUBSCRIPTION_KEY'],
    'api_user' => $_ENV['MOMO_COLLECTION_API_USER'],
    'api_key' => $_ENV['MOMO_COLLECTION_API_KEY'],
    'target_environment' => TARGET_ENVIRONMENT,
    'base_url' => BASE_URL
]);

// Disbursement API Configuration
define('DISBURSEMENT_CONFIG', [
    'subscription_key' => $_ENV['MOMO_DISBURSEMENT_SUBSCRIPTION_KEY'],
    'api_user' => $_ENV['MOMO_DISBURSEMENT_API_USER'],
    'api_key' => $_ENV['MOMO_DISBURSEMENT_API_KEY'],
    'target_environment' => TARGET_ENVIRONMENT,
    'base_url' => BASE_URL
]);

// Validate production settings
if (ENVIRONMENT === 'production') {
    if (TARGET_ENVIRONMENT !== 'mtnrwanda') {
        throw new Exception("Production must use 'mtnrwanda' as target environment");
    }
}

// Log configuration (without sensitive data)
error_log(sprintf(
    "MoMo Config: Environment=%s, Target=%s, Currency=%s",
    ENVIRONMENT,
    TARGET_ENVIRONMENT,
    CURRENCY
));
