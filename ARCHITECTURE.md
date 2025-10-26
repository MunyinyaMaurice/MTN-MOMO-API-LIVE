# 🏗️ MTN MoMo API Architecture

## System Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                         YOUR SERVER                              │
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │                    Web Interface                          │  │
│  │                                                           │  │
│  │  ┌─────────────┐     ┌──────────────────────────────┐   │  │
│  │  │             │     │                              │   │  │
│  │  │  index.php  │     │   MTN_MoMo_Postman_Collection│   │  │
│  │  │  (Dashboard)│     │   (Import to Postman)        │   │  │
│  │  │             │     │                              │   │  │
│  │  └──────┬──────┘     └──────────────┬───────────────┘   │  │
│  │         │                           │                   │  │
│  │         └───────────────┬───────────┘                   │  │
│  └─────────────────────────┼───────────────────────────────┘  │
│                            │                                   │
│  ┌─────────────────────────▼───────────────────────────────┐  │
│  │                   API Router (api.php)                   │  │
│  │                                                          │  │
│  │  Endpoints:                                              │  │
│  │  • GET  ?action=verify                                   │  │
│  │  • GET  ?action=help                                     │  │
│  │  • GET  ?action=collection/balance                       │  │
│  │  • POST ?action=collection/request-to-pay                │  │
│  │  • GET  ?action=collection/transaction-status            │  │
│  │  • GET  ?action=disbursement/balance                     │  │
│  │  • POST ?action=disbursement/transfer                    │  │
│  │  • POST ?action=test/payment                             │  │
│  │                                                          │  │
│  └────────────┬────────────────────────┬───────────────────┘  │
│               │                        │                       │
│  ┌────────────▼──────────┐  ┌──────────▼──────────────────┐  │
│  │  MoMoCollection.php   │  │  MoMoDisbursement.php       │  │
│  │  (Receive Payments)   │  │  (Send Money)               │  │
│  │                       │  │                             │  │
│  │  • getAccessToken()   │  │  • getAccessToken()         │  │
│  │  • getAccountBalance()│  │  • getAccountBalance()      │  │
│  │  • requestToPay()     │  │  • transfer()               │  │
│  │  • getTransactionSta()│  │  • getTransactionStatus()   │  │
│  │  • isAccountActive()  │  │                             │  │
│  └────────────┬──────────┘  └──────────┬──────────────────┘  │
│               │                        │                       │
│  ┌────────────▼────────────────────────▼───────────────────┐  │
│  │              config.php (Configuration)                  │  │
│  │                                                          │  │
│  │  • Loads .env credentials                                │  │
│  │  • Sets COLLECTION_CONFIG                                │  │
│  │  • Sets DISBURSEMENT_CONFIG                              │  │
│  │  • Validates settings                                    │  │
│  └────────────┬─────────────────────────────────────────────┘  │
│               │                                                 │
│  ┌────────────▼─────────────────────────────────────────────┐  │
│  │              .env (Credentials)                          │  │
│  │                                                          │  │
│  │  MOMO_ENVIRONMENT=production                             │  │
│  │  MOMO_TARGET_ENVIRONMENT=mtnrwanda                       │  │
│  │  MOMO_COLLECTION_SUBSCRIPTION_KEY=2bfc85f2...           │  │
│  │  MOMO_COLLECTION_API_USER=8f399903...                    │  │
│  │  MOMO_COLLECTION_API_KEY=b7c38044...                     │  │
│  │  MOMO_DISBURSEMENT_SUBSCRIPTION_KEY=43a217b6...          │  │
│  │  MOMO_DISBURSEMENT_API_USER=8f399903...                  │  │
│  │  MOMO_DISBURSEMENT_API_KEY=b7c38044...                   │  │
│  │                                                          │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
└─────────────────────────┬───────────────────────────────────────┘
                          │
                          │ HTTPS Request
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│                    MTN MoMo API                                  │
│              (proxy.momoapi.mtn.co.rw)                           │
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │                 Collection Product                        │  │
│  │  • POST /collection/token/                                │  │
│  │  • GET  /collection/v1_0/account/balance                  │  │
│  │  • POST /collection/v1_0/requesttopay                     │  │
│  │  • GET  /collection/v1_0/requesttopay/{refId}             │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │               Disbursement Product                        │  │
│  │  • POST /disbursement/token/                              │  │
│  │  • GET  /disbursement/v1_0/account/balance                │  │
│  │  • POST /disbursement/v1_0/transfer                       │  │
│  │  • GET  /disbursement/v1_0/transfer/{refId}               │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
└─────────────────────────┬───────────────────────────────────────┘
                          │
                          │ Mobile Network
                          │
                          ▼
                  ┌───────────────┐
                  │ Customer Phone│
                  │ 250782752491  │
                  │               │
                  │ MTN MoMo      │
                  │ [Enter PIN]   │
                  └───────────────┘
```

---

## Request Flow for Payment

```
Step 1: CLIENT REQUEST
┌─────────────┐
│   Postman   │──────┐
└─────────────┘      │
                     │ POST ?action=collection/request-to-pay
┌─────────────┐      │ Body: {amount: 100, phone: 250782752491}
│   Browser   │──────┤
└─────────────┘      │
                     │
┌─────────────┐      │
│  Your App   │──────┘
└─────────────┘
         │
         ▼
┌────────────────────────────────────────┐
│  YOUR SERVER                           │
│  api.php → MoMoCollection.php          │
│                                        │
│  1. Validate input                     │
│  2. Get access token (cached)          │
│  3. Generate reference ID              │
│  4. Build request payload              │
└────────────┬───────────────────────────┘
             │
             │ HTTPS POST
             │ Headers:
             │  - Authorization: Bearer {token}
             │  - X-Reference-Id: {uuid}
             │  - X-Target-Environment: mtnrwanda
             │  - Ocp-Apim-Subscription-Key: {key}
             │
             ▼
┌────────────────────────────────────────┐
│  MTN MoMo API                          │
│  proxy.momoapi.mtn.co.rw               │
│                                        │
│  1. Validate request                   │
│  2. Check account balance              │
│  3. Send payment request to network    │
│  4. Return 202 Accepted                │
└────────────┬───────────────────────────┘
             │
             │ MTN Network
             │
             ▼
┌────────────────────────────────────────┐
│  Customer Phone (250782752491)         │
│                                        │
│  ┌──────────────────────────────────┐  │
│  │  MTN Mobile Money                │  │
│  │  Payment Request                 │  │
│  │                                  │  │
│  │  Amount: 100 RWF                 │  │
│  │  From: DEEPNEXIS                 │  │
│  │                                  │  │
│  │  [Enter PIN to approve]          │  │
│  └──────────────────────────────────┘  │
└────────────┬───────────────────────────┘
             │
             │ User enters PIN
             │
             ▼
┌────────────────────────────────────────┐
│  MTN Network                           │
│                                        │
│  1. Validate PIN                       │
│  2. Check available balance            │
│  3. Process transaction                │
│  4. Update balances                    │
│  5. Set status to SUCCESSFUL           │
└────────────┬───────────────────────────┘
             │
             │ Status Update
             │
             ▼
┌────────────────────────────────────────┐
│  YOUR SERVER                           │
│                                        │
│  Poll status with:                     │
│  GET ?action=collection/transaction-   │
│      status&reference_id={uuid}        │
│                                        │
│  Response:                             │
│  {                                     │
│    "status": "SUCCESSFUL",             │
│    "amount": "100",                    │
│    "currency": "RWF"                   │
│  }                                     │
└────────────┬───────────────────────────┘
             │
             │ JSON Response
             │
             ▼
┌────────────────────────────────────────┐
│  CLIENT                                │
│                                        │
│  ✅ Payment Successful!                │
│  Reference ID: {uuid}                  │
│  Amount: 100 RWF                       │
│  Time: 15 seconds                      │
└────────────────────────────────────────┘
```

---

## File Dependencies

```
api.php
  │
  ├─── requires: config.php
  │       │
  │       └─── requires: .env
  │       └─── requires: vendor/autoload.php
  │
  ├─── requires: MoMoCollection.php
  │
  └─── requires: MoMoDisbursement.php


index.php
  └─── standalone (visual dashboard)


MTN_MoMo_Postman_Collection.json
  └─── standalone (import to Postman)


.htaccess
  └─── standalone (Apache config)


composer.json + composer.lock
  └─── install with: composer install
       creates: vendor/ directory
```

---

## Data Flow

```
1. CONFIGURATION LOADING
   .env → config.php → Constants (COLLECTION_CONFIG, DISBURSEMENT_CONFIG)

2. API REQUEST
   HTTP Request → api.php → Route to correct endpoint

3. AUTHENTICATION
   MoMoCollection/Disbursement → getAccessToken()
   → MTN API /token/ → Bearer Token (cached for 1 hour)

4. API CALL
   MoMoCollection → requestToPay()
   → MTN API /requesttopay → HTTP 202 + Reference ID

5. STATUS CHECK
   MoMoCollection → getTransactionStatus()
   → MTN API /requesttopay/{refId} → Transaction Status

6. RESPONSE
   JSON formatted response → Client (Postman/Browser/App)
```

---

## Security Layers

```
┌────────────────────────────────────────┐
│  Layer 1: Web Server (.htaccess)      │
│  • Block .env file access              │
│  • Block composer.json access          │
│  • Enable CORS for API                 │
└────────────┬───────────────────────────┘
             │
             ▼
┌────────────────────────────────────────┐
│  Layer 2: API Router (api.php)         │
│  • Input validation                    │
│  • Required field checks               │
│  • Error handling                      │
└────────────┬───────────────────────────┘
             │
             ▼
┌────────────────────────────────────────┐
│  Layer 3: Business Logic               │
│  • Phone format validation             │
│  • Currency validation (RWF only)      │
│  • Amount validation (> 0)             │
└────────────┬───────────────────────────┘
             │
             ▼
┌────────────────────────────────────────┐
│  Layer 4: API Communication            │
│  • HTTPS only                          │
│  • SSL verification enabled            │
│  • Token-based authentication          │
│  • Subscription key authentication     │
└────────────┬───────────────────────────┘
             │
             ▼
┌────────────────────────────────────────┐
│  Layer 5: MTN API                      │
│  • OAuth2 authentication               │
│  • API key validation                  │
│  • Target environment verification     │
│  • Rate limiting                       │
└────────────────────────────────────────┘
```

---

## Testing Flow

```
TEST 1: VERIFY CREDENTIALS
GET /api.php?action=verify
   │
   ├─ Test Collection Token   → ✅ PASS
   ├─ Test Collection Balance → ✅ PASS
   ├─ Test Disbursement Token → ✅ PASS
   └─ Test Disbursement Balance → ✅ PASS


TEST 2: CHECK BALANCE
GET /api.php?action=collection/balance
   │
   └─ Returns: 1000.00 RWF


TEST 3: REQUEST PAYMENT
POST /api.php?action=collection/request-to-pay
   │
   ├─ Send request → HTTP 202
   ├─ Get reference_id → Save it
   └─ Check phone for prompt


TEST 4: USER APPROVES
Phone (250782752491)
   │
   ├─ MTN prompt appears
   ├─ Enter PIN
   └─ Payment approved


TEST 5: CHECK STATUS
GET /api.php?action=collection/transaction-status
   │
   └─ Status: SUCCESSFUL


TEST 6: VERIFY BALANCE
GET /api.php?action=collection/balance
   │
   └─ Returns: 1100.00 RWF (increased by 100)
```

---

## Environment Variables Flow

```
.env file
   │
   ├─ MOMO_ENVIRONMENT=production
   ├─ MOMO_TARGET_ENVIRONMENT=mtnrwanda
   ├─ MOMO_BASE_URL=https://proxy.momoapi.mtn.co.rw
   ├─ MOMO_COLLECTION_SUBSCRIPTION_KEY=***
   ├─ MOMO_COLLECTION_API_USER=***
   ├─ MOMO_COLLECTION_API_KEY=***
   ├─ MOMO_DISBURSEMENT_SUBSCRIPTION_KEY=***
   ├─ MOMO_DISBURSEMENT_API_USER=***
   └─ MOMO_DISBURSEMENT_API_KEY=***
         │
         ▼
   config.php loads with Dotenv
         │
         ├─ define('ENVIRONMENT', 'production')
         ├─ define('TARGET_ENVIRONMENT', 'mtnrwanda')
         ├─ define('CURRENCY', 'RWF')
         ├─ define('COLLECTION_CONFIG', [...])
         └─ define('DISBURSEMENT_CONFIG', [...])
               │
               ▼
   Used by MoMoCollection & MoMoDisbursement classes
```

---

## Quick Reference

### Important URLs
- **API Base:** `http://yourserver.com/momo/api.php`
- **Dashboard:** `http://yourserver.com/momo/index.php`
- **MTN API:** `https://proxy.momoapi.mtn.co.rw`

### Test Credentials
- **Phone:** 250782752491
- **Test Amount:** 100 RWF
- **Currency:** RWF

### Key Response Codes
- `200` - Success
- `202` - Accepted (async operation)
- `400` - Bad Request
- `401` - Unauthorized
- `404` - Not Found

### Transaction Statuses
- `PENDING` - Waiting for approval
- `SUCCESSFUL` - Completed
- `FAILED` - Failed
- `REJECTED` - User rejected

---

This architecture is:
- ✅ Clean and modular
- ✅ Easy to understand
- ✅ Secure by design
- ✅ Postman-ready
- ✅ Production-ready
