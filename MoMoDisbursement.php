<?php
/**
 * MTN MoMo Disbursement API
 * For sending money to customers
 */

class MoMoDisbursement {
    private $baseUrl;
    private $subscriptionKey;
    private $targetEnvironment;
    private $apiUser;
    private $apiKey;
    private $accessToken;
    private $tokenExpiry;

    public function __construct($config) {
        $this->baseUrl = $config['base_url'];
        $this->subscriptionKey = $config['subscription_key'];
        $this->apiUser = $config['api_user'];
        $this->apiKey = $config['api_key'];
        $this->targetEnvironment = $config['target_environment'];
    }

    /**
     * Get access token (cached)
     */
    public function getAccessToken() {
        if ($this->accessToken && $this->tokenExpiry > time()) {
            return $this->accessToken;
        }

        $url = $this->baseUrl . '/disbursement/token/';
        $auth = base64_encode($this->apiUser . ':' . $this->apiKey);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $auth,
                'Ocp-Apim-Subscription-Key: ' . $this->subscriptionKey,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Failed to get access token. HTTP $httpCode: $response");
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new Exception("No access token in response");
        }

        $this->accessToken = $data['access_token'];
        $this->tokenExpiry = time() + ($data['expires_in'] ?? 3600);

        return $this->accessToken;
    }

    /**
     * Get account balance
     */
    public function getAccountBalance() {
        $token = $this->getAccessToken();
        $url = $this->baseUrl . '/disbursement/v1_0/account/balance';

        return $this->makeRequest('GET', $url, null, [
            'Authorization: Bearer ' . $token,
            'X-Target-Environment: ' . $this->targetEnvironment,
            'Ocp-Apim-Subscription-Key: ' . $this->subscriptionKey
        ]);
    }

    /**
     * Transfer money to customer
     */
    public function transfer(
        $amount,
        $externalId,
        $phone,
        $currency = 'RWF',
        $payerMessage = '',
        $payeeNote = '',
        $callbackUrl = null
    ) {
        // Validate phone number for Rwanda
        if ($this->targetEnvironment === 'mtnrwanda') {
            if (!preg_match('/^250\d{9}$/', $phone)) {
                throw new Exception("Invalid Rwanda phone format. Use: 250XXXXXXXXX");
            }
            if ($currency !== 'RWF') {
                throw new Exception("Currency must be RWF for Rwanda");
            }
        }

        $token = $this->getAccessToken();
        $referenceId = $this->generateUUID();
        $url = $this->baseUrl . '/disbursement/v1_0/transfer';

        $headers = [
            'Authorization: Bearer ' . $token,
            'X-Reference-Id: ' . $referenceId,
            'X-Target-Environment: ' . $this->targetEnvironment,
            'Ocp-Apim-Subscription-Key: ' . $this->subscriptionKey,
            'Content-Type: application/json'
        ];

        if ($callbackUrl) {
            $headers[] = 'X-Callback-Url: ' . $callbackUrl;
        }

        $payload = [
            'amount' => (string)$amount,
            'currency' => $currency,
            'externalId' => $externalId,
            'payee' => [
                'partyIdType' => 'MSISDN',
                'partyId' => $phone
            ],
            'payerMessage' => $payerMessage,
            'payeeNote' => $payeeNote
        ];

        $response = $this->makeRequest('POST', $url, $payload, $headers);

        // Get initial status
        sleep(1);
        $status = $this->getTransactionStatus($referenceId);

        return [
            'referenceId' => $referenceId,
            'response' => $response,
            'status' => $status
        ];
    }

    /**
     * Get transaction status
     */
    public function getTransactionStatus($referenceId) {
        $token = $this->getAccessToken();
        $url = $this->baseUrl . "/disbursement/v1_0/transfer/{$referenceId}";

        return $this->makeRequest('GET', $url, null, [
            'Authorization: Bearer ' . $token,
            'X-Target-Environment: ' . $this->targetEnvironment,
            'Ocp-Apim-Subscription-Key: ' . $this->subscriptionKey
        ]);
    }

    /**
     * Make HTTP request
     */
    private function makeRequest($method, $url, $data = null, $headers = []) {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADER => true
        ]);

        if ($data && in_array($method, ['POST', 'PUT'])) {
            $json = json_encode($data);
            $headers[] = 'Content-Length: ' . strlen($json);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Request failed: $error");
        }

        // Extract headers and body
        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        // Extract correlation ID
        $correlationId = null;
        if (preg_match('/X-CorrelationID:\s*([^\r\n]+)/i', $responseHeaders, $matches)) {
            $correlationId = trim($matches[1]);
        }

        return [
            'http_code' => $httpCode,
            'data' => json_decode($responseBody, true),
            'raw_response' => $responseBody,
            'correlation_id' => $correlationId
        ];
    }

    /**
     * Generate UUID
     */
    private function generateUUID() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
