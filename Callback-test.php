<?php

/**
 * Callback Testing & Diagnostics Tool
 * DEEPNEXIS Ltd
 * 
 * Tests callback endpoint accessibility and simulates MTN callbacks
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

/**
 * Send JSON response
 */
function sendResponse($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit();
}

switch ($action) {

    case 'test-callback':
        // Simulate an MTN callback to test your callback.php endpoint
        $testCallback = [
            'amount' => '100',
            'currency' => 'RWF',
            'externalId' => 'test_' . time(),
            'referenceId' => sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff)
            ),
            'status' => 'SUCCESSFUL',
            'financialTransactionId' => 'FT' . time(),
            'payer' => [
                'partyIdType' => 'MSISDN',
                'partyId' => '250782752491'
            ]
        ];

        // Send POST request to callback.php
        $ch = curl_init(BASE_URL . '/callback.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($testCallback),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: MTN-MoMo-Test-Agent'
            ],
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        sendResponse([
            'test' => 'callback_simulation',
            'callback_url' => BASE_URL . '/callback.php',
            'test_data' => $testCallback,
            'response' => [
                'http_code' => $httpCode,
                'body' => json_decode($response, true) ?? $response,
                'error' => $error ?: null
            ],
            'status' => $httpCode === 200 ? 'PASS' : 'FAIL',
            'message' => $httpCode === 200
                ? 'Callback endpoint is working correctly!'
                : "Callback endpoint returned HTTP $httpCode"
        ]);
        break;

    case 'check-accessibility':
        // Check if callback endpoint is accessible
        $callbackUrl = BASE_URL . '/callback.php';

        $ch = curl_init($callbackUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        $tests = [
            'dns_resolution' => [
                'status' => $info['namelookup_time'] > 0 ? 'PASS' : 'FAIL',
                'time' => round($info['namelookup_time'], 3) . 's'
            ],
            'connection' => [
                'status' => $info['connect_time'] > 0 ? 'PASS' : 'FAIL',
                'time' => round($info['connect_time'], 3) . 's'
            ],
            'ssl_handshake' => [
                'status' => strpos($callbackUrl, 'https') !== false && $info['appconnect_time'] > 0 ? 'PASS' : 'N/A',
                'time' => round($info['appconnect_time'], 3) . 's'
            ],
            'http_response' => [
                'status' => $httpCode >= 200 && $httpCode < 300 ? 'PASS' : 'FAIL',
                'code' => $httpCode
            ],
            'total_time' => round($info['total_time'], 3) . 's'
        ];

        $allPassed = !in_array('FAIL', array_column($tests, 'status'));

        sendResponse([
            'test' => 'accessibility_check',
            'callback_url' => $callbackUrl,
            'tests' => $tests,
            'response_body' => json_decode($response, true) ?? substr($response, 0, 200),
            'overall_status' => $allPassed ? 'PASS' : 'FAIL',
            'message' => $allPassed
                ? 'Callback endpoint is publicly accessible!'
                : 'Callback endpoint has accessibility issues',
            'error' => $error ?: null
        ]);
        break;

    case 'view-logs':
        // View recent callback logs
        $logFile = __DIR__ . '/logs/callbacks.log';
        $debugLog = __DIR__ . '/logs/callback_debug.log';
        $errorLog = __DIR__ . '/logs/callback_errors.log';

        $logs = [
            'callbacks' => file_exists($logFile)
                ? array_slice(explode('---', file_get_contents($logFile)), -5)
                : ['No callback logs yet'],
            'debug' => file_exists($debugLog)
                ? array_slice(explode('---', file_get_contents($debugLog)), -5)
                : ['No debug logs yet'],
            'errors' => file_exists($errorLog)
                ? array_slice(explode('---', file_get_contents($errorLog)), -3)
                : ['No error logs yet']
        ];

        sendResponse([
            'test' => 'view_logs',
            'log_location' => __DIR__ . '/logs/',
            'recent_logs' => $logs,
            'message' => 'Showing last 5 entries from each log file'
        ]);
        break;

    case 'check-permissions':
        // Check file permissions
        $logDir = __DIR__ . '/logs';
        $callbackFile = __DIR__ . '/callback.php';

        $checks = [
            'logs_directory' => [
                'path' => $logDir,
                'exists' => is_dir($logDir),
                'writable' => is_writable($logDir),
                'permissions' => is_dir($logDir) ? substr(sprintf('%o', fileperms($logDir)), -4) : 'N/A'
            ],
            'callback_file' => [
                'path' => $callbackFile,
                'exists' => file_exists($callbackFile),
                'readable' => is_readable($callbackFile),
                'permissions' => file_exists($callbackFile) ? substr(sprintf('%o', fileperms($callbackFile)), -4) : 'N/A'
            ]
        ];

        sendResponse([
            'test' => 'permissions_check',
            'checks' => $checks,
            'message' => 'File permissions diagnostic'
        ]);
        break;

    case 'provider-callback-info':
        // Information about Provider Callback Host
        sendResponse([
            'test' => 'provider_callback_info',
            'message' => 'MTN Provider Callback Host Configuration',
            'info' => [
                'what_is_it' => 'Provider Callback Host is a URL you configure ONCE in MTN\'s system that applies to ALL transactions',
                'difference' => [
                    'provider_callback_host' => 'Set once in MTN partners portal - applies to all transactions',
                    'x_callback_url_header' => 'Set per transaction - only works if Provider Callback Host is configured'
                ],
                'your_callback_url' => BASE_URL . '/callback.php',
                'configuration_required' => true,
                'how_to_configure' => [
                    '1. Login to MTN MoMo Partners Portal',
                    '2. Go to your API Credentials section',
                    '3. Find "Provider Callback Host" or "Notification URL" setting',
                    '4. Enter: ' . BASE_URL . '/callback.php',
                    '5. Save and wait for approval (if required)',
                    '6. Test with a real transaction'
                ],
                'alternative_method' => [
                    'description' => 'If you cannot find Provider Callback Host in portal, contact MTN support',
                    'email' => 'api.support@mtn.com (or Rwanda specific support)',
                    'provide_info' => [
                        'API User ID: ' . COLLECTION_CONFIG['api_user'],
                        'Callback URL: ' . BASE_URL . '/callback.php',
                        'Environment: ' . TARGET_ENVIRONMENT
                    ]
                ]
            ],
            'current_status' => [
                'callback_url_accessible' => 'Unknown - run ?action=check-accessibility to test',
                'provider_host_configured' => 'Unknown - check MTN portal or contact support'
            ]
        ]);
        break;

    case 'help':
    default:
        sendResponse([
            'tool' => 'Callback Testing & Diagnostics',
            'available_actions' => [
                'check-accessibility' => [
                    'description' => 'Check if callback endpoint is publicly accessible',
                    'url' => '?action=check-accessibility',
                    'method' => 'GET'
                ],
                'test-callback' => [
                    'description' => 'Simulate an MTN callback to test callback.php',
                    'url' => '?action=test-callback',
                    'method' => 'GET'
                ],
                'view-logs' => [
                    'description' => 'View recent callback logs',
                    'url' => '?action=view-logs',
                    'method' => 'GET'
                ],
                'check-permissions' => [
                    'description' => 'Check file and directory permissions',
                    'url' => '?action=check-permissions',
                    'method' => 'GET'
                ],
                'provider-callback-info' => [
                    'description' => 'Learn about Provider Callback Host configuration',
                    'url' => '?action=provider-callback-info',
                    'method' => 'GET'
                ]
            ],
            'callback_endpoint' => BASE_URL . '/callback.php',
            'quick_tests' => [
                '1. Check accessibility: ?action=check-accessibility',
                '2. Get Provider info: ?action=provider-callback-info',
                '3. Test callback: ?action=test-callback',
                '4. View logs: ?action=view-logs'
            ]
        ]);
        break;
}
