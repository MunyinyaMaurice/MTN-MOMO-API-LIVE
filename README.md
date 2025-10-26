# MTN MoMo API - DEEPNEXIS Ltd

## Clean, Professional, Postman-Ready Implementation

### 🎯 Overview

This is a clean, professional implementation of the MTN MoMo API for Rwanda production environment. All code is optimized for testing with Postman using your phone number **250782752491** with **REAL MONEY**.

---

## 📁 Project Structure

```
momo-api/
├── api.php                          # Main API router (all endpoints)
├── config.php                       # Configuration management
├── MoMoCollection.php               # Collection API class
├── MoMoDisbursement.php             # Disbursement API class
├── .env                             # Environment variables (credentials)
├── composer.json                    # Dependencies
├── MTN_MoMo_Postman_Collection.json # Import this into Postman
└── README.md                        # This file
```

---

## 🚀 Quick Setup

### Step 1: Install Dependencies

```bash
composer install
```

### Step 2: Verify Configuration

Your `.env` file is already configured with your credentials:

- ✅ Collection Key: 2bfc85f27df14fc18f6b2bda8639732a
- ✅ Disbursement Key: 43a217b615874b9698f9794de7a43417
- ✅ API User: 8f399903-38c6-4f89-9c91-8ffb53760998
- ✅ Test Phone: 250782752491 `You can changes this number to any active mtn rwanda phone number you may like`
- ✅ Environment: Production (Rwanda)

### Step 3: Deploy to Server

Upload these files to your web server:

```bash
# Upload all files to your server
scp -r * user@yourserver.com:/var/www/html/momo/
```

### Step 4: Import into Postman

1. Open Postman
2. Click "Import"
3. Select `MTN_MoMo_Postman_Collection.json`
4. Update the `base_url` variable to your server URL
5. Start testing!

---

## 📡 API Endpoints

### Base URL

```
https://mtn-momo-api-live-production.up.railway.app/api.php
```

### Available Endpoints

#### 🔍 General

- `GET  ?action=help` - API documentation
- `GET  ?action=verify` - Verify credentials

#### 💰 Collection API (Receive Payments)

- `GET  ?action=collection/token` - Get access token
- `GET  ?action=collection/balance` - Get account balance
- `GET  ?action=collection/account-active&phone=250xxx` - Check if account is active
- `GET  ?action=collection/account-info&phone=250xxx` - Get account info
- `POST ?action=collection/request-to-pay` - Request payment (REAL MONEY)
- `GET  ?action=collection/transaction-status&reference_id=xxx` - Get transaction status

#### 💸 Disbursement API (Send Money)

- `GET  ?action=disbursement/token` - Get access token
- `GET  ?action=disbursement/balance` - Get account balance
- `POST ?action=disbursement/transfer` - Transfer money (REAL MONEY)
- `GET  ?action=disbursement/transaction-status&reference_id=xxx` - Get transfer status

#### 🧪 Testing

- `POST ?action=test/payment` - Complete payment test (request + wait + verify)

---

## 📝 Postman Examples

### 1. Verify Your Setup

```
GET https://mtn-momo-api-live-production.up.railway.app/api.php?action=verify
```

**Expected Response:**

```json
{
  "success": true,
  "environment": "production",
  "target_environment": "mtnrwanda",
  "currency": "RWF",
  "tests": {
    "collection_auth": "PASS",
    "collection_balance": "PASS",
    "disbursement_auth": "PASS",
    "disbursement_balance": "PASS"
  }
}
```

### 2. Check Your Balance

```
GET https://mtn-momo-api-live-production.up.railway.app/api.php?action=collection/balance
```

**Expected Response:**

```json
{
  "success": true,
  "data": {
    "availableBalance": "1000.00",
    "currency": "RWF"
  },
  "http_code": 200
}
```

### 3. Request Payment (REAL MONEY)

```
POST https://mtn-momo-api-live-production.up.railway.app/api.php?action=collection/request-to-pay
Content-Type: application/json

{
  "amount": "100",
  "phone": "250782752491",
  "external_id": "test_12345",
  "currency": "RWF",
  "payer_message": "Test payment",
  "payee_note": "Testing"
}
```

**Expected Response:**

```json
{
  "success": true,
  "reference_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "external_id": "test_12345",
  "status": {
    "data": {
      "status": "PENDING"
    }
  },
  "http_code": 202
}
```

**Important:** After requesting payment:

1. Check your phone (250782752491)
2. You'll receive an MTN MoMo prompt
3. Enter your PIN to approve
4. Use the `reference_id` to check status

### 4. Check Transaction Status

```
GET https://mtn-momo-api-live-production.up.railway.app/api.php?action=collection/transaction-status&reference_id=YOUR_REFERENCE_ID
```

**Expected Response (Successful):**

```json
{
  "success": true,
  "reference_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "data": {
    "amount": "100",
    "currency": "RWF",
    "financialTransactionId": "123456789",
    "externalId": "test_12345",
    "payer": {
      "partyIdType": "MSISDN",
      "partyId": "250782752491"
    },
    "status": "SUCCESSFUL"
  }
}
```

### 5. Complete Payment Test (Automated)

```
POST https://mtn-momo-api-live-production.up.railway.app/api.php?action=test/payment
Content-Type: application/json

{
  "phone": "250782752491",
  "amount": "100"
}
```

This endpoint will:

1. Request payment
2. Wait for you to approve on your phone
3. Check status every 2 seconds
4. Return final result

**Expected Response:**

```json
{
  "success": true,
  "reference_id": "a06290c0-3fc8-4e73-b0e1-5d1b6ffa2024",
  "external_id": "test_1761518688",
  "amount": "100",
  "phone": "250782752491",
  "final_status": "SUCCESSFUL",
  "attempts": 5,
  "time_taken": "10 seconds",
  "timestamp": "2025-10-26 22:45:03"
}
```

---

## ⚠️ Important Notes for Real Money Testing

### 1. Phone Number Format

- Must be: `250XXXXXXXXX` (12 digits)
- Your number: `250782752491`
- Wrong: `0782752491` or `+250782752491`

### 2. Currency

- **Must be RWF** for Rwanda
- Never use EUR, USD, or other currencies

### 3. Transaction Flow

```
1. Request payment (HTTP 202)
2. Check phone for MTN prompt
3. Enter PIN to approve
4. Wait 5-20 seconds
5. Check transaction status
6. Status becomes SUCCESSFUL
```

### 4. Correlation ID

- Every API response includes a `correlation_id`
- **Save this ID** if you encounter errors
- Use it when contacting MTN support

---

## 🧪 Testing Workflow

### Step-by-Step Test Process

#### Test 1: Verify Credentials

```
1. In Postman: GET ?action=verify
2. ✅ All tests should show "PASS"
3. If any fail, check your .env credentials
```

#### Test 2: Check Balance

```
1. GET ?action=collection/balance
2. Note your current balance
3. You'll verify this changes after payment
```

#### Test 3: Small Payment (100 RWF)

```
1. POST ?action=collection/request-to-pay
   Body: {"amount": "100", "phone": "250782752491", "external_id": "test_001"}
2. Copy the reference_id from response
3. Check your phone for MTN prompt
4. Enter PIN to approve payment
```

#### Test 4: Verify Payment

```
1. Wait 10 seconds
2. GET ?action=collection/transaction-status&reference_id=YOUR_REF_ID
3. Status should be "SUCCESSFUL"
4. Check balance again - should increase by 100 RWF
```

#### Test 5: Automated Test (Optional)

```
1. POST ?action=test/payment
   Body: {"phone": "250782752491", "amount": "100"}
2. Approve on phone when prompted
3. Endpoint waits and checks status automatically
4. Returns final result
```

---

## 🔧 Troubleshooting

### Error: "Failed to get access token" (HTTP 401)

**Problem:** Invalid credentials
**Solution:**

1. Check API User and API Key in .env
2. Verify subscription key is correct
3. Make sure credentials are for production (not sandbox)(generated from partners dashboard)

### Error: "Target environment not found" (HTTP 404)

**Problem:** Wrong target environment
**Solution:**

1. Must be exactly: `mtnrwanda` (lowercase, no spaces)
2. Check .env file: `MOMO_TARGET_ENVIRONMENT=mtnrwanda`

### Error: "Invalid phone number format"

**Problem:** Phone number format is wrong
**Solution:**

1. Must be: 250782752491 (12 digits)
2. No spaces, no +, no dashes
3. Must start with 250

### Payment stuck in "PENDING"

**Problem:** User hasn't approved on phone
**Solution:**

1. Check phone for MTN MoMo prompt
2. Enter PIN to approve
3. Wait 30-60 seconds
4. Check status again

### Error: "Currency must be RWF"

**Problem:** Wrong currency specified
**Solution:**

1. Always use "RWF" for Rwanda
2. Update request body: `"currency": "RWF"`

---

## 📊 Response Status Codes

| Code | Meaning      | Action                                 |
| ---- | ------------ | -------------------------------------- |
| 200  | Success      | Request completed successfully         |
| 202  | Accepted     | Payment request accepted, check status |
| 400  | Bad Request  | Check request parameters               |
| 401  | Unauthorized | Invalid credentials                    |
| 404  | Not Found    | Wrong endpoint or target environment   |
| 409  | Conflict     | Duplicate transaction                  |
| 500  | Server Error | API issue, contact MTN support         |

---

## 🎓 Key Concepts

### Reference ID

- Unique identifier for each transaction
- Generated by your system
- Used to track transaction status
- Format: UUID (e.g., `a1b2c3d4-e5f6-7890-abcd-ef1234567890`)

### External ID

- Your internal transaction ID
- Can be anything (string)
- Helps you match MoMo transactions to your system
- Example: `order_12345`, `invoice_6789`

### Correlation ID

- Generated by MTN API
- Returned in response headers
- **Critical for support requests**
- Always log this value

### Transaction Statuses

- `PENDING` - Waiting for user approval
- `SUCCESSFUL` - Payment completed
- `FAILED` - Payment failed
- `REJECTED` - User rejected payment

---

## 📄 License & Credits

**Company:** DEEPNEXIS Ltd  
**Environment:** Rwanda Production  
**API Provider:** MTN MoMo  
**Currency:** RWF (Rwandan Francs)

---
