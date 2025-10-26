# 🚀 Quick Start Guide - Testing MTN MoMo with Postman

## ⚡ 5-Minute Setup

### Step 1: Install Dependencies (1 minute)
```bash
cd /path/to/your/project
composer install
```

### Step 2: Upload to Server (2 minutes)
Upload all files to your web server. Your files:
- `api.php` - Main API endpoint
- `config.php` - Configuration
- `MoMoCollection.php` - Collection API
- `MoMoDisbursement.php` - Disbursement API
- `.env` - Your credentials (already configured)
- `vendor/` folder (from composer install)

### Step 3: Import to Postman (1 minute)
1. Open Postman
2. Click **Import** button
3. Select `MTN_MoMo_Postman_Collection.json`
4. Edit collection variables:
   - Set `base_url` to your server URL (e.g., `http://yourserver.com/momo`)
   - `test_phone` is already set to `250782752491`
   - `test_amount` is set to `100`

### Step 4: Test! (1 minute)
Start with these 3 requests in order:

---

## 🧪 Your First 3 Tests

### Test 1: Verify Credentials ✅
**Endpoint:** `GET ?action=verify`

**What it does:** Tests all your credentials and API connectivity

**Expected:** All tests show "PASS"

**Postman Steps:**
1. Open "1. Verify Credentials" request
2. Click **Send**
3. Check response - should see `"success": true`

---

### Test 2: Check Your Balance 💰
**Endpoint:** `GET ?action=collection/balance`

**What it does:** Shows your current MoMo account balance

**Postman Steps:**
1. Open "Get Collection Balance" request
2. Click **Send**
3. Note your current balance (you'll verify this changes)

**Example Response:**
```json
{
  "success": true,
  "data": {
    "availableBalance": "1000.00",
    "currency": "RWF"
  }
}
```

---

### Test 3: Request Payment (REAL MONEY!) 💸
**Endpoint:** `POST ?action=collection/request-to-pay`

**What it does:** Requests 100 RWF from your phone number

**Postman Steps:**
1. Open "Request Payment (Request to Pay)" request
2. Check the Body tab - should show:
   ```json
   {
     "amount": "100",
     "phone": "250782752491",
     "external_id": "test_{{$timestamp}}",
     "currency": "RWF",
     "payer_message": "Payment for testing",
     "payee_note": "Test transaction"
   }
   ```
3. Click **Send**
4. **IMMEDIATELY check your phone (250782752491)**
5. You'll get an MTN MoMo payment prompt
6. Enter your PIN to approve
7. Copy the `reference_id` from Postman response

**Example Response:**
```json
{
  "success": true,
  "reference_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "external_id": "test_1234567890",
  "status": {
    "data": {
      "status": "PENDING"
    }
  },
  "http_code": 202,
  "correlation_id": "12345678-1234-1234-1234-123456789012"
}
```

**⚠️ IMPORTANT:**
- Save the `reference_id` - you'll need it next
- Save the `correlation_id` - needed if there's an error

---

## 🔍 Test 4: Check Payment Status

**Endpoint:** `GET ?action=collection/transaction-status&reference_id=YOUR_REF_ID`

**Postman Steps:**
1. Open "Get Transaction Status" request
2. In the URL, replace `YOUR_REFERENCE_ID` with the actual reference_id from Test 3
3. Click **Send**
4. Status should be `"SUCCESSFUL"`

**If status is still PENDING:**
- Wait 10 seconds
- Click **Send** again
- Repeat until status changes

**Successful Response:**
```json
{
  "success": true,
  "reference_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "data": {
    "amount": "100",
    "currency": "RWF",
    "externalId": "test_1234567890",
    "payer": {
      "partyId": "250782752491"
    },
    "status": "SUCCESSFUL"
  }
}
```

---

## 🎯 Test 5: Verify Balance Changed

**Endpoint:** `GET ?action=collection/balance`

**Postman Steps:**
1. Open "Get Collection Balance" request again
2. Click **Send**
3. Compare with balance from Test 2
4. Should have increased by 100 RWF

---

## 🚀 Bonus: Automated Complete Test

Want to test everything automatically?

**Endpoint:** `POST ?action=test/payment`

**Postman Steps:**
1. Open "Complete Payment Test" request
2. Body should show:
   ```json
   {
     "phone": "250782752491",
     "amount": "100"
   }
   ```
3. Click **Send**
4. Check your phone and approve
5. Wait... (endpoint will auto-check status every 2 seconds)
6. Get complete result!

**This endpoint will:**
- ✅ Request payment
- ✅ Wait for approval
- ✅ Check status automatically
- ✅ Return final result

**Example Response:**
```json
{
  "success": true,
  "reference_id": "xxx-xxx-xxx",
  "amount": "100",
  "phone": "250782752491",
  "final_status": "SUCCESSFUL",
  "attempts": 8,
  "time_taken": "16 seconds"
}
```

---

## 📱 What to Expect on Your Phone

When you request a payment, you'll receive:

```
MTN Mobile Money
Payment Request

Amount: 100 RWF
From: DEEPNEXIS
Message: Payment for testing

1. Enter PIN to approve
2. Press * to cancel
```

**Just enter your PIN and wait!**

---

## ✅ Success Checklist

After running all tests, you should have:

- [x] Verified credentials (all PASS)
- [x] Checked initial balance
- [x] Requested payment (HTTP 202)
- [x] Approved payment on phone
- [x] Verified status = SUCCESSFUL
- [x] Confirmed balance increased by 100 RWF
- [x] Saved correlation_id for records

---

## 🔥 Common Issues & Quick Fixes

### Issue 1: "Failed to get access token"
**Fix:** Check your .env file has correct credentials

### Issue 2: "Invalid phone number format"
**Fix:** Phone must be: 250782752491 (no spaces, no +)

### Issue 3: Payment stuck in PENDING
**Fix:** 
1. Check your phone for MTN prompt
2. Enter PIN to approve
3. Wait 30 seconds
4. Check status again

### Issue 4: No phone prompt received
**Fix:**
1. Check phone number is correct (250782752491)
2. Verify phone has active MoMo account
3. Check phone has network signal
4. Try again with test amount

---

## 💡 Pro Tips

1. **Always start with 100 RWF** for testing
2. **Save correlation_id** from every request
3. **Check balance** before and after tests
4. **Use the automated test** for faster testing
5. **Test during office hours** (better support)

---

## 📞 Need Help?

### Check Your Setup
```
GET http://yourserver.com/momo/api.php?action=verify
```

If all tests PASS, your setup is correct!

### Get API Documentation
```
GET http://yourserver.com/momo/api.php?action=help
```

Shows all available endpoints.

---

## 🎓 Understanding the Flow

```
You (Postman) → Your API → MTN API → MTN Server
                    ↓
              Your Phone ← MTN Network
                    ↓
              You Enter PIN
                    ↓
              MTN confirms payment
                    ↓
         Status changes to SUCCESSFUL
```

---

## 📊 Testing Progress

| Test | Endpoint | Status | Amount |
|------|----------|--------|--------|
| 1. Verify | GET verify | ⏳ Pending | - |
| 2. Balance | GET balance | ⏳ Pending | - |
| 3. Payment | POST request-to-pay | ⏳ Pending | 100 RWF |
| 4. Status | GET transaction-status | ⏳ Pending | - |
| 5. Verify | GET balance | ⏳ Pending | - |

Check off each test as you complete it!

---

## 🚀 Ready to Start?

1. Open Postman
2. Import the collection
3. Run Test 1: Verify
4. If it passes → Continue with Test 2
5. If it fails → Check .env credentials

**Let's test with real money!** 💰

---

**Your Details:**
- Phone: 250782752491
- Test Amount: 100 RWF
- Currency: RWF
- Environment: Production (Rwanda)

**All set! Start testing now!** 🎉
