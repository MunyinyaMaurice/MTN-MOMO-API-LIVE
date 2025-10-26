<?php

/**
 * MTN MoMo Webhook/Callback Receiver
 * DEEPNEXIS Ltd - Production
 * 
 * This endpoint receives payment notifications from MTN MoMo API
 * when transactions complete (success, failure, or rejection)
 */

// Load configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/transaction_logger.php';

// Log directory setup
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logFile = $logDir . '/callbacks.log';
$errorLog = $logDir . '/callback_errors.log';

/**
 * Log callback data
 */
function logCallback($type, $data)
{
    global $logFile;

    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => $type,
        'data' => $data,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];

    file_put_contents(
        $logFile,
        json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n---\n",
        FILE_APPEND
    );
}

/**
 * Log error
 */
function logError($message, $context = [])
{
    global $errorLog;

    $errorEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $message,
        'context' => $context
    ];

    file_put_contents(
        $errorLog,
        json_encode($errorEntry, JSON_PRETTY_PRINT) . "\n---\n",
        FILE_APPEND
    );
}

/**
 * Send JSON response
 */
function sendResponse($success, $message = '', $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logError('Invalid request method', ['method' => $_SERVER['REQUEST_METHOD']]);
        sendResponse(false, 'Only POST method allowed', 405);
    }

    // Get raw callback data
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        logError('Empty request body');
        sendResponse(false, 'Empty request body', 400);
    }

    // Parse JSON
    $callbackData = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logError('Invalid JSON', ['error' => json_last_error_msg(), 'raw' => $rawInput]);
        sendResponse(false, 'Invalid JSON format', 400);
    }

    // Log all callbacks for debugging
    logCallback('received', [
        'headers' => getallheaders(),
        'body' => $callbackData
    ]);

    // Validate required fields
    if (!isset($callbackData['referenceId'])) {
        logError('Missing referenceId', $callbackData);
        sendResponse(false, 'Missing referenceId', 400);
    }

    if (!isset($callbackData['status'])) {
        logError('Missing status', $callbackData);
        sendResponse(false, 'Missing status', 400);
    }

    // Extract callback data
    $referenceId = $callbackData['referenceId'];
    $status = $callbackData['status'];
    $amount = $callbackData['amount'] ?? null;
    $currency = $callbackData['currency'] ?? 'RWF';
    $externalId = $callbackData['externalId'] ?? null;
    $financialTransactionId = $callbackData['financialTransactionId'] ?? null;
    $reason = $callbackData['reason'] ?? null;

    // Log to transaction logger
    $logger = new TransactionLogger();
    $logger->logCallback($referenceId, $status, [
        'amount' => $amount,
        'currency' => $currency,
        'external_id' => $externalId,
        'financial_transaction_id' => $financialTransactionId,
        'reason' => $reason,
        'callback_time' => date('Y-m-d H:i:s')
    ]);

    // Process based on status
    switch ($status) {
        case 'SUCCESSFUL':
            logCallback('success', [
                'reference_id' => $referenceId,
                'external_id' => $externalId,
                'amount' => $amount,
                'financial_transaction_id' => $financialTransactionId
            ]);

            // TODO: Add your business logic here
            // Examples:
            // - Update order status in database
            // - Send confirmation email to customer
            // - Trigger fulfillment process
            // - Update inventory

            break;

        case 'FAILED':
            logCallback('failed', [
                'reference_id' => $referenceId,
                'external_id' => $externalId,
                'reason' => $reason
            ]);

            // TODO: Handle failed payment
            // Examples:
            // - Notify customer of failure
            // - Update order status to "payment failed"
            // - Log for manual review

            break;

        case 'REJECTED':
            logCallback('rejected', [
                'reference_id' => $referenceId,
                'external_id' => $externalId,
                'reason' => $reason
            ]);

            // TODO: Handle rejected payment
            // Examples:
            // - Notify customer they rejected payment
            // - Cancel pending order
            // - Free up reserved inventory

            break;

        case 'PENDING':
            logCallback('pending', [
                'reference_id' => $referenceId,
                'external_id' => $externalId
            ]);

            // Typically you won't get PENDING callbacks, but handle just in case
            break;

        default:
            logError('Unknown status', ['status' => $status, 'data' => $callbackData]);
            break;
    }

    // ALWAYS respond with 200 OK to acknowledge receipt
    // If you don't, MTN will retry the callback multiple times
    sendResponse(true, 'Callback received and processed', 200);
} catch (Exception $e) {
    logError('Exception in callback handler', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

    // Still send 200 OK to avoid retries for processing errors
    // But log the error for investigation
    sendResponse(true, 'Callback received but processing error occurred', 200);
}
