<?php

/**
 * MTN MoMo Webhook/Callback Receiver - FIXED VERSION
 * DEEPNEXIS Ltd - Production
 * 
 * This version handles MTN's actual webhook format properly
 */

// Load configuration if available
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

if (file_exists(__DIR__ . '/transaction_logger.php')) {
    require_once __DIR__ . '/transaction_logger.php';
}

// Log directory setup
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logFile = $logDir . '/callbacks.log';
$errorLog = $logDir . '/callback_errors.log';
$debugLog = $logDir . '/callback_debug.log';

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
 * Log debug info
 */
function logDebug($data)
{
    global $debugLog;

    file_put_contents(
        $debugLog,
        "=== " . date('Y-m-d H:i:s') . " ===" . PHP_EOL .
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL .
            "===================" . PHP_EOL . PHP_EOL,
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
    // Log all incoming requests for debugging
    logDebug([
        'method' => $_SERVER['REQUEST_METHOD'],
        'headers' => getallheaders(),
        'raw_input' => file_get_contents('php://input'),
        'get' => $_GET,
        'post' => $_POST,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown'
    ]);

    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logError('Invalid request method', ['method' => $_SERVER['REQUEST_METHOD']]);
        sendResponse(false, 'Only POST method allowed', 405);
    }

    // Get raw callback data
    $rawInput = file_get_contents('php://input');

    // MTN might send empty body initially or use different format
    // So we accept empty body but log it
    if (empty($rawInput)) {
        logCallback('empty_body', [
            'note' => 'Received empty body - this might be MTN testing the endpoint',
            'headers' => getallheaders(),
            'method' => $_SERVER['REQUEST_METHOD']
        ]);

        // Still return 200 OK to MTN
        sendResponse(true, 'Callback received (empty body)', 200);
    }

    // Try to parse JSON
    $callbackData = json_decode($rawInput, true);

    // If not valid JSON, log raw data and still accept it
    if (json_last_error() !== JSON_ERROR_NONE) {
        logCallback('invalid_json', [
            'error' => json_last_error_msg(),
            'raw_input' => $rawInput,
            'headers' => getallheaders()
        ]);

        // Still return 200 OK to avoid MTN retries
        sendResponse(true, 'Callback received (invalid JSON)', 200);
    }

    // Log all callbacks for debugging
    logCallback('received', [
        'headers' => getallheaders(),
        'body' => $callbackData
    ]);

    // Check if we have essential data
    $referenceId = $callbackData['referenceId'] ?? $callbackData['reference_id'] ?? null;
    $status = $callbackData['status'] ?? null;

    // If we have reference ID and status, process normally
    if ($referenceId && $status) {
        $amount = $callbackData['amount'] ?? null;
        $currency = $callbackData['currency'] ?? 'RWF';
        $externalId = $callbackData['externalId'] ?? $callbackData['external_id'] ?? null;
        $financialTransactionId = $callbackData['financialTransactionId'] ??
            $callbackData['financial_transaction_id'] ?? null;
        $reason = $callbackData['reason'] ?? null;

        // Log to transaction logger if available
        if (class_exists('TransactionLogger')) {
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
            } catch (Exception $e) {
                logError('Transaction logger error', ['error' => $e->getMessage()]);
            }
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
                logCallback('unknown_status', [
                    'status' => $status,
                    'reference_id' => $referenceId,
                    'data' => $callbackData
                ]);
                break;
        }
    } else {
        // We got data but missing essential fields
        logCallback('incomplete_data', [
            'note' => 'Missing referenceId or status',
            'data' => $callbackData
        ]);
    }

    // ALWAYS respond with 200 OK to acknowledge receipt
    // MTN will retry if we don't respond with 200
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

// OLD VERSION

// /**
//  * MTN MoMo Webhook/Callback Receiver
//  * DEEPNEXIS Ltd - Production
//  * 
//  * This endpoint receives payment notifications from MTN MoMo API
//  * when transactions complete (success, failure, or rejection)
//  */

// // Load configuration
// require_once __DIR__ . '/config.php';
// require_once __DIR__ . '/transaction_logger.php';

// // Log directory setup
// $logDir = __DIR__ . '/logs';
// if (!is_dir($logDir)) {
//     mkdir($logDir, 0755, true);
// }

// $logFile = $logDir . '/callbacks.log';
// $errorLog = $logDir . '/callback_errors.log';

// /**
//  * Log callback data
//  */
// function logCallback($type, $data)
// {
//     global $logFile;

//     $logEntry = [
//         'timestamp' => date('Y-m-d H:i:s'),
//         'type' => $type,
//         'data' => $data,
//         'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
//         'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
//     ];

//     file_put_contents(
//         $logFile,
//         json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n---\n",
//         FILE_APPEND
//     );
// }

// /**
//  * Log error
//  */
// function logError($message, $context = [])
// {
//     global $errorLog;

//     $errorEntry = [
//         'timestamp' => date('Y-m-d H:i:s'),
//         'error' => $message,
//         'context' => $context
//     ];

//     file_put_contents(
//         $errorLog,
//         json_encode($errorEntry, JSON_PRETTY_PRINT) . "\n---\n",
//         FILE_APPEND
//     );
// }

// /**
//  * Send JSON response
//  */
// function sendResponse($success, $message = '', $code = 200)
// {
//     http_response_code($code);
//     header('Content-Type: application/json');
//     echo json_encode([
//         'success' => $success,
//         'message' => $message,
//         'timestamp' => date('Y-m-d H:i:s')
//     ]);
//     exit();
// }

// try {
//     // Only accept POST requests
//     if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//         logError('Invalid request method', ['method' => $_SERVER['REQUEST_METHOD']]);
//         sendResponse(false, 'Only POST method allowed', 405);
//     }

//     // Get raw callback data
//     $rawInput = file_get_contents('php://input');
//     if (empty($rawInput)) {
//         logError('Empty request body');
//         sendResponse(false, 'Empty request body', 400);
//     }

//     // Parse JSON
//     $callbackData = json_decode($rawInput, true);
//     if (json_last_error() !== JSON_ERROR_NONE) {
//         logError('Invalid JSON', ['error' => json_last_error_msg(), 'raw' => $rawInput]);
//         sendResponse(false, 'Invalid JSON format', 400);
//     }

//     // Log all callbacks for debugging
//     logCallback('received', [
//         'headers' => getallheaders(),
//         'body' => $callbackData
//     ]);

//     // Validate required fields
//     if (!isset($callbackData['referenceId'])) {
//         logError('Missing referenceId', $callbackData);
//         sendResponse(false, 'Missing referenceId', 400);
//     }

//     if (!isset($callbackData['status'])) {
//         logError('Missing status', $callbackData);
//         sendResponse(false, 'Missing status', 400);
//     }

//     // Extract callback data
//     $referenceId = $callbackData['referenceId'];
//     $status = $callbackData['status'];
//     $amount = $callbackData['amount'] ?? null;
//     $currency = $callbackData['currency'] ?? 'RWF';
//     $externalId = $callbackData['externalId'] ?? null;
//     $financialTransactionId = $callbackData['financialTransactionId'] ?? null;
//     $reason = $callbackData['reason'] ?? null;

//     // Log to transaction logger
//     $logger = new TransactionLogger();
//     $logger->logCallback($referenceId, $status, [
//         'amount' => $amount,
//         'currency' => $currency,
//         'external_id' => $externalId,
//         'financial_transaction_id' => $financialTransactionId,
//         'reason' => $reason,
//         'callback_time' => date('Y-m-d H:i:s')
//     ]);

//     // Process based on status
//     switch ($status) {
//         case 'SUCCESSFUL':
//             logCallback('success', [
//                 'reference_id' => $referenceId,
//                 'external_id' => $externalId,
//                 'amount' => $amount,
//                 'financial_transaction_id' => $financialTransactionId
//             ]);

//             // TODO: Add your business logic here
//             // Examples:
//             // - Update order status in database
//             // - Send confirmation email to customer
//             // - Trigger fulfillment process
//             // - Update inventory

//             break;

//         case 'FAILED':
//             logCallback('failed', [
//                 'reference_id' => $referenceId,
//                 'external_id' => $externalId,
//                 'reason' => $reason
//             ]);

//             // TODO: Handle failed payment
//             // Examples:
//             // - Notify customer of failure
//             // - Update order status to "payment failed"
//             // - Log for manual review

//             break;

//         case 'REJECTED':
//             logCallback('rejected', [
//                 'reference_id' => $referenceId,
//                 'external_id' => $externalId,
//                 'reason' => $reason
//             ]);

//             // TODO: Handle rejected payment
//             // Examples:
//             // - Notify customer they rejected payment
//             // - Cancel pending order
//             // - Free up reserved inventory

//             break;

//         case 'PENDING':
//             logCallback('pending', [
//                 'reference_id' => $referenceId,
//                 'external_id' => $externalId
//             ]);

//             // Typically you won't get PENDING callbacks, but handle just in case
//             break;

//         default:
//             logError('Unknown status', ['status' => $status, 'data' => $callbackData]);
//             break;
//     }

//     // ALWAYS respond with 200 OK to acknowledge receipt
//     // If you don't, MTN will retry the callback multiple times
//     sendResponse(true, 'Callback received and processed', 200);
// } catch (Exception $e) {
//     logError('Exception in callback handler', [
//         'message' => $e->getMessage(),
//         'file' => $e->getFile(),
//         'line' => $e->getLine()
//     ]);

//     // Still send 200 OK to avoid retries for processing errors
//     // But log the error for investigation
//     sendResponse(true, 'Callback received but processing error occurred', 200);
// }
