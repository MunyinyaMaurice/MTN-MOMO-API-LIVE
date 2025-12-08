<?php

/**
 * MTN MoMo Webhook/Callback Receiver - ENHANCED VERSION
 * DEEPNEXIS Ltd - Production
 * 
 * This endpoint receives payment notifications from MTN MoMo API
 * Includes enhanced logging and diagnostics
 */

// Load configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/transaction_logger.php';

// CRITICAL: Log directory setup (with proper permissions)
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
    chmod($logDir, 0777);
}

$logFile = $logDir . '/callbacks.log';
$errorLog = $logDir . '/callback_errors.log';
$debugLog = $logDir . '/callback_debug.log';

// Ensure log files exist with write permissions
foreach ([$logFile, $errorLog, $debugLog] as $file) {
    if (!file_exists($file)) {
        touch($file);
        chmod($file, 0666);
    }
}

/**
 * Enhanced logging function
 */
function logDebug($message, $data = [])
{
    global $debugLog;

    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s.u'),
        'message' => $message,
        'data' => $data,
        'server' => [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]
    ];

    @file_put_contents(
        $debugLog,
        json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n---\n",
        FILE_APPEND | LOCK_EX
    );
}

/**
 * Log callback data
 */
function logCallback($type, $data)
{
    global $logFile;

    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s.u'),
        'type' => $type,
        'data' => $data,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];

    @file_put_contents(
        $logFile,
        json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n---\n",
        FILE_APPEND | LOCK_EX
    );
}

/**
 * Log error
 */
function logError($message, $context = [])
{
    global $errorLog;

    $errorEntry = [
        'timestamp' => date('Y-m-d H:i:s.u'),
        'error' => $message,
        'context' => $context
    ];

    @file_put_contents(
        $errorLog,
        json_encode($errorEntry, JSON_PRETTY_PRINT) . "\n---\n",
        FILE_APPEND | LOCK_EX
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

// Log ALL incoming requests (even GET requests for testing)
logDebug('Incoming request', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders(),
    'query' => $_GET,
    'raw_input' => file_get_contents('php://input')
]);

try {
    // Handle GET requests (for testing accessibility)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        logDebug('GET request received - callback endpoint is accessible');
        sendResponse(true, 'Callback endpoint is accessible and working. Waiting for MTN POST callbacks.', 200);
    }

    // Only accept POST requests for actual callbacks
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logError('Invalid request method', ['method' => $_SERVER['REQUEST_METHOD']]);
        sendResponse(false, 'Only POST method allowed for callbacks', 405);
    }

    // Get raw callback data
    $rawInput = file_get_contents('php://input');

    logDebug('POST request body', ['raw_input' => $rawInput]);

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

    logDebug('Parsed callback data', [
        'reference_id' => $referenceId,
        'status' => $status,
        'amount' => $amount,
        'external_id' => $externalId
    ]);

    // Log to transaction logger
    try {
        $logger = new TransactionLogger();
        $logger->logCallback($referenceId, $status, [
            'amount' => $amount,
            'currency' => $currency,
            'external_id' => $externalId,
            'financial_transaction_id' => $financialTransactionId,
            'reason' => $reason,
            'callback_time' => date('Y-m-d H:i:s')
        ]);
        logDebug('Callback logged to database successfully');
    } catch (Exception $e) {
        logError('Failed to log callback to database', [
            'error' => $e->getMessage(),
            'reference_id' => $referenceId
        ]);
    }

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

            break;

        case 'REJECTED':
            logCallback('rejected', [
                'reference_id' => $referenceId,
                'external_id' => $externalId,
                'reason' => $reason
            ]);

            // TODO: Handle rejected payment

            break;

        case 'PENDING':
            logCallback('pending', [
                'reference_id' => $referenceId,
                'external_id' => $externalId
            ]);
            break;

        default:
            logError('Unknown status', ['status' => $status, 'data' => $callbackData]);
            break;
    }

    // ALWAYS respond with 200 OK to acknowledge receipt
    logDebug('Sending 200 OK response');
    sendResponse(true, 'Callback received and processed', 200);
} catch (Exception $e) {
    logError('Exception in callback handler', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);

    // Still send 200 OK to avoid retries for processing errors
    sendResponse(true, 'Callback received but processing error occurred', 200);
}
