<?php
/**
 * MTN MoMo API - Postman Ready Endpoints
 * DEEPNEXIS Ltd - Production API
 * 
 * All endpoints return JSON responses
 */

// Enable CORS for Postman
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load dependencies
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/MoMoCollection.php';
require_once __DIR__ . '/MoMoDisbursement.php';

// Get request data
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Response helper
function sendResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit();
}

// Error handler
function sendError($message, $code = 400, $details = null) {
    $response = [
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    if ($details) $response['details'] = $details;
    sendResponse($response, $code);
}

try {
    // Route requests
    switch ($path) {
        
        // ============================================================
        // COLLECTION API ENDPOINTS
        // ============================================================
        
        case 'collection/token':
            // Get access token
            $collection = new MoMoCollection(COLLECTION_CONFIG);
            $token = $collection->getAccessToken();
            sendResponse([
                'success' => true,
                'access_token' => $token,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'collection/balance':
            // Get account balance
            $collection = new MoMoCollection(COLLECTION_CONFIG);
            $result = $collection->getAccountBalance();
            sendResponse([
                'success' => $result['http_code'] === 200,
                'data' => $result['data'],
                'http_code' => $result['http_code'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'collection/request-to-pay':
            // Request payment from customer
            if ($method !== 'POST') {
                sendError('POST method required', 405);
            }
            
            // Validate required fields
            $required = ['amount', 'phone', 'external_id'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    sendError("Missing required field: $field", 400);
                }
            }
            
            $collection = new MoMoCollection(COLLECTION_CONFIG);
            $result = $collection->requestToPay(
                $input['amount'],
                $input['external_id'],
                $input['phone'],
                $input['currency'] ?? 'RWF',
                $input['payer_message'] ?? 'Payment request',
                $input['payee_note'] ?? 'Payment',
                $input['callback_url'] ?? null
            );
            
            sendResponse([
                'success' => $result['response']['http_code'] === 202,
                'reference_id' => $result['referenceId'],
                'external_id' => $input['external_id'],
                'status' => $result['status'],
                'http_code' => $result['response']['http_code'],
                'correlation_id' => $result['response']['correlation_id'] ?? null,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'collection/transaction-status':
            // Get transaction status
            $referenceId = $input['reference_id'] ?? $_GET['reference_id'] ?? null;
            if (!$referenceId) {
                sendError('reference_id is required', 400);
            }
            
            $collection = new MoMoCollection(COLLECTION_CONFIG);
            $result = $collection->getTransactionStatus($referenceId);
            
            sendResponse([
                'success' => $result['http_code'] === 200,
                'reference_id' => $referenceId,
                'data' => $result['data'],
                'http_code' => $result['http_code'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'collection/account-active':
            // Check if account is active
            $phone = $input['phone'] ?? $_GET['phone'] ?? null;
            if (!$phone) {
                sendError('phone number is required', 400);
            }
            
            $collection = new MoMoCollection(COLLECTION_CONFIG);
            $result = $collection->isAccountActive($phone);
            
            sendResponse([
                'success' => $result['http_code'] === 200,
                'phone' => $phone,
                'is_active' => $result['data']['result'] ?? false,
                'http_code' => $result['http_code'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'collection/account-info':
            // Get account holder info
            $phone = $input['phone'] ?? $_GET['phone'] ?? null;
            if (!$phone) {
                sendError('phone number is required', 400);
            }
            
            $collection = new MoMoCollection(COLLECTION_CONFIG);
            $result = $collection->getAccountHolderInfo($phone);
            
            sendResponse([
                'success' => $result['http_code'] === 200,
                'phone' => $phone,
                'data' => $result['data'],
                'http_code' => $result['http_code'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        // ============================================================
        // DISBURSEMENT API ENDPOINTS
        // ============================================================
        
        case 'disbursement/token':
            // Get access token
            $disbursement = new MoMoDisbursement(DISBURSEMENT_CONFIG);
            $token = $disbursement->getAccessToken();
            sendResponse([
                'success' => true,
                'access_token' => $token,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'disbursement/balance':
            // Get account balance
            $disbursement = new MoMoDisbursement(DISBURSEMENT_CONFIG);
            $result = $disbursement->getAccountBalance();
            sendResponse([
                'success' => $result['http_code'] === 200,
                'data' => $result['data'],
                'http_code' => $result['http_code'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'disbursement/transfer':
            // Transfer money to customer
            if ($method !== 'POST') {
                sendError('POST method required', 405);
            }
            
            // Validate required fields
            $required = ['amount', 'phone', 'external_id'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    sendError("Missing required field: $field", 400);
                }
            }
            
            $disbursement = new MoMoDisbursement(DISBURSEMENT_CONFIG);
            $result = $disbursement->transfer(
                $input['amount'],
                $input['external_id'],
                $input['phone'],
                $input['currency'] ?? 'RWF',
                $input['payer_message'] ?? 'Transfer',
                $input['payee_note'] ?? 'Payment transfer',
                $input['callback_url'] ?? null
            );
            
            sendResponse([
                'success' => $result['response']['http_code'] === 202,
                'reference_id' => $result['referenceId'],
                'external_id' => $input['external_id'],
                'status' => $result['status'],
                'http_code' => $result['response']['http_code'],
                'correlation_id' => $result['response']['correlation_id'] ?? null,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'disbursement/transaction-status':
            // Get transaction status
            $referenceId = $input['reference_id'] ?? $_GET['reference_id'] ?? null;
            if (!$referenceId) {
                sendError('reference_id is required', 400);
            }
            
            $disbursement = new MoMoDisbursement(DISBURSEMENT_CONFIG);
            $result = $disbursement->getTransactionStatus($referenceId);
            
            sendResponse([
                'success' => $result['http_code'] === 200,
                'reference_id' => $referenceId,
                'data' => $result['data'],
                'http_code' => $result['http_code'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        // ============================================================
        // TEST & UTILITY ENDPOINTS
        // ============================================================
        
        case 'test/payment':
            // Complete payment test (request + wait + status check)
            if ($method !== 'POST') {
                sendError('POST method required', 405);
            }
            
            $phone = $input['phone'] ?? '250782752491';
            $amount = $input['amount'] ?? '100';
            $externalId = 'test_' . time();
            
            $collection = new MoMoCollection(COLLECTION_CONFIG);
            
            // Request payment
            $result = $collection->requestToPay(
                $amount,
                $externalId,
                $phone,
                'RWF',
                'Test payment',
                'Testing MTN MoMo'
            );
            
            if ($result['response']['http_code'] !== 202) {
                sendError('Payment request failed', 400, $result);
            }
            
            $referenceId = $result['referenceId'];
            
            // Wait and check status
            $maxAttempts = 30; // 60 seconds (30 attempts * 2 seconds)
            $status = 'PENDING';
            
            for ($i = 0; $i < $maxAttempts; $i++) {
                sleep(2);
                $statusResult = $collection->getTransactionStatus($referenceId);
                
                if (isset($statusResult['data']['status'])) {
                    $status = $statusResult['data']['status'];
                    if (in_array($status, ['SUCCESSFUL', 'FAILED', 'REJECTED'])) {
                        break;
                    }
                }
            }
            
            sendResponse([
                'success' => $status === 'SUCCESSFUL',
                'reference_id' => $referenceId,
                'external_id' => $externalId,
                'amount' => $amount,
                'phone' => $phone,
                'final_status' => $status,
                'attempts' => $i + 1,
                'time_taken' => ($i + 1) * 2 . ' seconds',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'verify':
            // Verify credentials and configuration
            $collection = new MoMoCollection(COLLECTION_CONFIG);
            $disbursement = new MoMoDisbursement(DISBURSEMENT_CONFIG);
            
            $tests = [];
            
            // Test 1: Collection token
            try {
                $collection->getAccessToken();
                $tests['collection_auth'] = 'PASS';
            } catch (Exception $e) {
                $tests['collection_auth'] = 'FAIL: ' . $e->getMessage();
            }
            
            // Test 2: Collection balance
            try {
                $balanceResult = $collection->getAccountBalance();
                $tests['collection_balance'] = $balanceResult['http_code'] === 200 ? 'PASS' : 'FAIL';
            } catch (Exception $e) {
                $tests['collection_balance'] = 'FAIL: ' . $e->getMessage();
            }
            
            // Test 3: Disbursement token
            try {
                $disbursement->getAccessToken();
                $tests['disbursement_auth'] = 'PASS';
            } catch (Exception $e) {
                $tests['disbursement_auth'] = 'FAIL: ' . $e->getMessage();
            }
            
            // Test 4: Disbursement balance
            try {
                $balanceResult = $disbursement->getAccountBalance();
                $tests['disbursement_balance'] = $balanceResult['http_code'] === 200 ? 'PASS' : 'FAIL';
            } catch (Exception $e) {
                $tests['disbursement_balance'] = 'FAIL: ' . $e->getMessage();
            }
            
            $allPassed = !in_array(false, array_map(function($v) {
                return strpos($v, 'PASS') !== false;
            }, $tests));
            
            sendResponse([
                'success' => $allPassed,
                'environment' => ENVIRONMENT,
                'target_environment' => TARGET_ENVIRONMENT,
                'currency' => CURRENCY,
                'base_url' => BASE_URL,
                'tests' => $tests,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case '':
        case 'help':
            // API documentation
            sendResponse([
                'api' => 'MTN MoMo API - DEEPNEXIS Ltd',
                'version' => '1.0',
                'environment' => ENVIRONMENT,
                'endpoints' => [
                    'Collection API' => [
                        'GET  /api.php?action=collection/token' => 'Get access token',
                        'GET  /api.php?action=collection/balance' => 'Get account balance',
                        'POST /api.php?action=collection/request-to-pay' => 'Request payment (requires: amount, phone, external_id)',
                        'GET  /api.php?action=collection/transaction-status&reference_id=xxx' => 'Get transaction status',
                        'GET  /api.php?action=collection/account-active&phone=250xxx' => 'Check if account is active',
                        'GET  /api.php?action=collection/account-info&phone=250xxx' => 'Get account holder info'
                    ],
                    'Disbursement API' => [
                        'GET  /api.php?action=disbursement/token' => 'Get access token',
                        'GET  /api.php?action=disbursement/balance' => 'Get account balance',
                        'POST /api.php?action=disbursement/transfer' => 'Transfer money (requires: amount, phone, external_id)',
                        'GET  /api.php?action=disbursement/transaction-status&reference_id=xxx' => 'Get transaction status'
                    ],
                    'Testing' => [
                        'POST /api.php?action=test/payment' => 'Complete payment test (optional: phone, amount)',
                        'GET  /api.php?action=verify' => 'Verify credentials and configuration'
                    ]
                ],
                'test_phone' => '250782752491',
                'currency' => CURRENCY
            ]);
            break;
            
        default:
            sendError('Invalid action. Use ?action=help for API documentation', 404);
    }
    
} catch (Exception $e) {
    sendError($e->getMessage(), 500, [
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
