<?php

/**
 * Transaction Logger and Analytics
 * DEEPNEXIS Ltd
 * 
 * Tracks all MTN MoMo transactions and provides reconciliation capabilities
 */

class TransactionLogger
{
    private $dbFile;
    private $db;

    public function __construct($dbPath = null)
    {
        $this->dbFile = $dbPath ?? __DIR__ . '/data/transactions.db';

        // Create data directory if it doesn't exist
        $dataDir = dirname($this->dbFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $this->initDatabase();
    }

    /**
     * Initialize database schema
     */
    private function initDatabase()
    {
        $this->db = new SQLite3($this->dbFile);

        // Enable foreign keys
        $this->db->exec('PRAGMA foreign_keys = ON');

        // Create transactions table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS transactions (
                reference_id TEXT PRIMARY KEY,
                external_id TEXT NOT NULL,
                amount TEXT NOT NULL,
                currency TEXT DEFAULT 'RWF',
                phone TEXT NOT NULL,
                status TEXT DEFAULT 'PENDING',
                payer_message TEXT,
                payee_note TEXT,
                callback_url TEXT,
                financial_transaction_id TEXT,
                failure_reason TEXT,
                
                -- Timing fields
                request_time DATETIME NOT NULL,
                completion_time DATETIME,
                callback_received_time DATETIME,
                duration_seconds INTEGER,
                callback_delay_seconds INTEGER,
                
                -- Tracking fields
                callback_received INTEGER DEFAULT 0,
                correlation_id TEXT,
                http_status_code INTEGER,
                
                -- Metadata
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create callbacks table (for multiple callbacks per transaction)
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS callbacks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reference_id TEXT NOT NULL,
                status TEXT NOT NULL,
                callback_data TEXT,
                received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                ip_address TEXT,
                FOREIGN KEY (reference_id) REFERENCES transactions(reference_id)
            )
        ");

        // Create indices for better query performance
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_status ON transactions(status)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_external_id ON transactions(external_id)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_request_time ON transactions(request_time)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_phone ON transactions(phone)");
    }

    /**
     * Log a payment request
     */
    public function logRequest(
        $referenceId,
        $externalId,
        $amount,
        $phone,
        $currency = 'RWF',
        $payerMessage = '',
        $payeeNote = '',
        $callbackUrl = null,
        $correlationId = null,
        $httpStatusCode = null
    ) {
        $stmt = $this->db->prepare("
            INSERT INTO transactions (
                reference_id, external_id, amount, currency, phone,
                payer_message, payee_note, callback_url, correlation_id,
                http_status_code, request_time, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')
        ");

        $requestTime = date('Y-m-d H:i:s');

        $stmt->bindValue(1, $referenceId, SQLITE3_TEXT);
        $stmt->bindValue(2, $externalId, SQLITE3_TEXT);
        $stmt->bindValue(3, $amount, SQLITE3_TEXT);
        $stmt->bindValue(4, $currency, SQLITE3_TEXT);
        $stmt->bindValue(5, $phone, SQLITE3_TEXT);
        $stmt->bindValue(6, $payerMessage, SQLITE3_TEXT);
        $stmt->bindValue(7, $payeeNote, SQLITE3_TEXT);
        $stmt->bindValue(8, $callbackUrl, SQLITE3_TEXT);
        $stmt->bindValue(9, $correlationId, SQLITE3_TEXT);
        $stmt->bindValue(10, $httpStatusCode, SQLITE3_INTEGER);
        $stmt->bindValue(11, $requestTime, SQLITE3_TEXT);

        return $stmt->execute();
    }

    /**
     * Log a callback received from MTN
     */
    public function logCallback($referenceId, $status, $callbackData = [])
    {
        $callbackTime = date('Y-m-d H:i:s');

        // Insert into callbacks table
        $stmt = $this->db->prepare("
            INSERT INTO callbacks (reference_id, status, callback_data, received_at, ip_address)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->bindValue(1, $referenceId, SQLITE3_TEXT);
        $stmt->bindValue(2, $status, SQLITE3_TEXT);
        $stmt->bindValue(3, json_encode($callbackData), SQLITE3_TEXT);
        $stmt->bindValue(4, $callbackTime, SQLITE3_TEXT);
        $stmt->bindValue(5, $_SERVER['REMOTE_ADDR'] ?? 'unknown', SQLITE3_TEXT);
        $stmt->execute();

        // Update main transaction record
        $stmt = $this->db->prepare("
            UPDATE transactions 
            SET 
                status = ?,
                completion_time = ?,
                callback_received_time = ?,
                callback_received = 1,
                financial_transaction_id = ?,
                failure_reason = ?,
                duration_seconds = (strftime('%s', ?) - strftime('%s', request_time)),
                callback_delay_seconds = (strftime('%s', ?) - strftime('%s', completion_time)),
                updated_at = ?
            WHERE reference_id = ?
        ");

        $financialId = $callbackData['financial_transaction_id'] ?? null;
        $reason = $callbackData['reason'] ?? null;

        $stmt->bindValue(1, $status, SQLITE3_TEXT);
        $stmt->bindValue(2, $callbackTime, SQLITE3_TEXT);
        $stmt->bindValue(3, $callbackTime, SQLITE3_TEXT);
        $stmt->bindValue(4, $financialId, SQLITE3_TEXT);
        $stmt->bindValue(5, $reason, SQLITE3_TEXT);
        $stmt->bindValue(6, $callbackTime, SQLITE3_TEXT);
        $stmt->bindValue(7, $callbackTime, SQLITE3_TEXT);
        $stmt->bindValue(8, $callbackTime, SQLITE3_TEXT);
        $stmt->bindValue(9, $referenceId, SQLITE3_TEXT);

        return $stmt->execute();
    }

    /**
     * Update transaction status (from manual polling)
     */
    public function updateStatus($referenceId, $status, $financialId = null, $reason = null)
    {
        $completionTime = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare("
            UPDATE transactions 
            SET 
                status = ?,
                completion_time = ?,
                financial_transaction_id = ?,
                failure_reason = ?,
                duration_seconds = (strftime('%s', ?) - strftime('%s', request_time)),
                updated_at = ?
            WHERE reference_id = ?
        ");

        $stmt->bindValue(1, $status, SQLITE3_TEXT);
        $stmt->bindValue(2, $completionTime, SQLITE3_TEXT);
        $stmt->bindValue(3, $financialId, SQLITE3_TEXT);
        $stmt->bindValue(4, $reason, SQLITE3_TEXT);
        $stmt->bindValue(5, $completionTime, SQLITE3_TEXT);
        $stmt->bindValue(6, $completionTime, SQLITE3_TEXT);
        $stmt->bindValue(7, $referenceId, SQLITE3_TEXT);

        return $stmt->execute();
    }

    /**
     * Get transaction by reference ID
     */
    public function getTransaction($referenceId)
    {
        $stmt = $this->db->prepare("SELECT * FROM transactions WHERE reference_id = ?");
        $stmt->bindValue(1, $referenceId, SQLITE3_TEXT);
        $result = $stmt->execute();

        return $result->fetchArray(SQLITE3_ASSOC);
    }

    /**
     * Get transaction by external ID
     */
    public function getTransactionByExternalId($externalId)
    {
        $stmt = $this->db->prepare("SELECT * FROM transactions WHERE external_id = ?");
        $stmt->bindValue(1, $externalId, SQLITE3_TEXT);
        $result = $stmt->execute();

        return $result->fetchArray(SQLITE3_ASSOC);
    }

    /**
     * Get all callbacks for a transaction
     */
    public function getCallbacks($referenceId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM callbacks 
            WHERE reference_id = ? 
            ORDER BY received_at DESC
        ");
        $stmt->bindValue(1, $referenceId, SQLITE3_TEXT);
        $result = $stmt->execute();

        $callbacks = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $callbacks[] = $row;
        }

        return $callbacks;
    }

    /**
     * Get statistics for all transactions
     */
    public function getStatistics($startDate = null, $endDate = null)
    {
        $dateFilter = '';
        if ($startDate && $endDate) {
            $dateFilter = "WHERE request_time BETWEEN '$startDate' AND '$endDate'";
        } elseif ($startDate) {
            $dateFilter = "WHERE request_time >= '$startDate'";
        } elseif ($endDate) {
            $dateFilter = "WHERE request_time <= '$endDate'";
        }

        $stats = [
            'total' => $this->db->querySingle("SELECT COUNT(*) FROM transactions $dateFilter"),
            'successful' => $this->db->querySingle("SELECT COUNT(*) FROM transactions $dateFilter AND status = 'SUCCESSFUL'"),
            'pending' => $this->db->querySingle("SELECT COUNT(*) FROM transactions $dateFilter AND status = 'PENDING'"),
            'failed' => $this->db->querySingle("SELECT COUNT(*) FROM transactions $dateFilter AND status = 'FAILED'"),
            'rejected' => $this->db->querySingle("SELECT COUNT(*) FROM transactions $dateFilter AND status = 'REJECTED'"),
            'callbacks_received' => $this->db->querySingle("SELECT COUNT(*) FROM transactions $dateFilter AND callback_received = 1"),
            'avg_duration' => $this->db->querySingle("SELECT AVG(duration_seconds) FROM transactions $dateFilter AND status = 'SUCCESSFUL'"),
            'max_duration' => $this->db->querySingle("SELECT MAX(duration_seconds) FROM transactions $dateFilter AND status = 'SUCCESSFUL'"),
            'min_duration' => $this->db->querySingle("SELECT MIN(duration_seconds) FROM transactions $dateFilter AND status = 'SUCCESSFUL'"),
            'total_amount' => $this->db->querySingle("SELECT SUM(CAST(amount AS REAL)) FROM transactions $dateFilter AND status = 'SUCCESSFUL'"),
            'avg_callback_delay' => $this->db->querySingle("SELECT AVG(callback_delay_seconds) FROM transactions $dateFilter AND callback_received = 1")
        ];

        // Calculate success rate
        $stats['success_rate'] = $stats['total'] > 0
            ? round(($stats['successful'] / $stats['total']) * 100, 2)
            : 0;

        // Calculate callback rate
        $stats['callback_rate'] = $stats['total'] > 0
            ? round(($stats['callbacks_received'] / $stats['total']) * 100, 2)
            : 0;

        // Round timing values
        $stats['avg_duration'] = round($stats['avg_duration'] ?? 0, 2);
        $stats['avg_callback_delay'] = round($stats['avg_callback_delay'] ?? 0, 2);

        return $stats;
    }

    /**
     * Get recent transactions
     */
    public function getRecentTransactions($limit = 10)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM transactions 
            ORDER BY request_time DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $transactions = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $transactions[] = $row;
        }

        return $transactions;
    }

    /**
     * Get pending transactions (older than X minutes)
     */
    public function getStuckTransactions($minutesOld = 5)
    {
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-$minutesOld minutes"));

        $stmt = $this->db->prepare("
            SELECT * FROM transactions 
            WHERE status = 'PENDING' 
            AND request_time < ?
            ORDER BY request_time ASC
        ");
        $stmt->bindValue(1, $cutoffTime, SQLITE3_TEXT);
        $result = $stmt->execute();

        $transactions = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $transactions[] = $row;
        }

        return $transactions;
    }

    /**
     * Get transactions by phone number
     */
    public function getTransactionsByPhone($phone, $limit = 20)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM transactions 
            WHERE phone = ?
            ORDER BY request_time DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $phone, SQLITE3_TEXT);
        $stmt->bindValue(2, $limit, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $transactions = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $transactions[] = $row;
        }

        return $transactions;
    }

    /**
     * Close database connection
     */
    public function __destruct()
    {
        if ($this->db) {
            $this->db->close();
        }
    }
}
