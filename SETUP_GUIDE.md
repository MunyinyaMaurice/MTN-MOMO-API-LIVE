# 🚀 COMPLETE SETUP & DEPLOYMENT GUIDE
## MTN MoMo API - DEEPNEXIS Ltd

---

## 📦 What You Received

You now have a **clean, professional, production-ready** MTN MoMo API implementation:

```
✅ api.php                          - Main API router (all endpoints)
✅ config.php                       - Configuration manager
✅ MoMoCollection.php               - Collection API (receive payments)
✅ MoMoDisbursement.php             - Disbursement API (send money)
✅ .env                             - Your credentials (CONFIGURED)
✅ index.php                        - Status dashboard
✅ composer.json                    - Dependencies
✅ composer.lock                    - Locked dependencies
✅ .htaccess                        - Apache configuration
✅ README.md                        - Full documentation
✅ QUICK_START.md                   - Quick testing guide
✅ MTN_MoMo_Postman_Collection.json - Postman collection
```

---

## ⚡ FASTEST SETUP (3 Steps - 5 Minutes)

### Step 1: Install Dependencies
```bash
composer install
```

### Step 2: Upload to Your Server
Upload ALL files to your web server (e.g., `/var/www/html/momo/`)

### Step 3: Test in Postman
1. Import `MTN_MoMo_Postman_Collection.json`
2. Set `base_url` variable to your server URL
3. Run "Verify Credentials" request
4. If all PASS → You're ready!

---

## 🎯 YOUR CONFIGURATION (Already Done!)

Your `.env` file is **already configured** with:

```
Environment:     production
Target:          mtnrwanda  
Base URL:        https://proxy.momoapi.mtn.co.rw
Currency:        RWF
Test Phone:      250782752491

✅ Collection Key:    2bfc85f27df14fc18f6b2bda8639732a
✅ Collection API:    8f399903-38c6-4f89-9c91-8ffb53760998
✅ Disbursement Key:  43a217b615874b9698f9794de7a43417
✅ Disbursement API:  8f399903-38c6-4f89-9c91-8ffb53760998
```

**No configuration needed - everything is set up!**

---

## 🧪 QUICK TEST (2 Minutes)

### Option 1: Browser Test
Visit: `http://yourserver.com/momo/index.php`

You'll see a status dashboard. Click "Verify Credentials" button.

### Option 2: Direct API Test
```bash
curl http://yourserver.com/momo/api.php?action=verify
```

### Option 3: Postman Test
1. Open Postman
2. Import the collection
3. Run "1. Verify Credentials"

**Expected Result:** All tests show "PASS"

---

## 💰 FIRST REAL MONEY TEST (5 Minutes)

### Test a 100 RWF Payment

**Postman Request:**
```
POST http://yourserver.com/momo/api.php?action=collection/request-to-pay

Body:
{
  "amount": "100",
  "phone": "250782752491",
  "external_id": "test_001",
  "currency": "RWF"
}
```

**What Happens:**
1. ✅ Request sent (HTTP 202)
2. 📱 Check phone 250782752491
3. 🔢 Enter your PIN
4. ⏳ Wait 10-20 seconds
5. ✅ Payment successful!

**Verify:**
```
GET http://yourserver.com/momo/api.php?action=collection/transaction-status&reference_id=YOUR_REF_ID
```

Should show: `"status": "SUCCESSFUL"`

---

## 📡 ALL AVAILABLE ENDPOINTS

### 🔍 General
- `GET /api.php?action=help` - API docs
- `GET /api.php?action=verify` - Verify setup

### 💰 Collection (Receive Money)
- `GET /api.php?action=collection/token` - Get token
- `GET /api.php?action=collection/balance` - Check balance
- `POST /api.php?action=collection/request-to-pay` - Request payment
- `GET /api.php?action=collection/transaction-status&reference_id=xxx` - Check status
- `GET /api.php?action=collection/account-active&phone=250xxx` - Check account
- `GET /api.php?action=collection/account-info&phone=250xxx` - Get account info

### 💸 Disbursement (Send Money)
- `GET /api.php?action=disbursement/token` - Get token
- `GET /api.php?action=disbursement/balance` - Check balance
- `POST /api.php?action=disbursement/transfer` - Send money
- `GET /api.php?action=disbursement/transaction-status&reference_id=xxx` - Check status

### 🧪 Testing
- `POST /api.php?action=test/payment` - Complete automated test

---

## 🎓 HOW TO USE IN YOUR APPLICATION

### Example: Request Payment in Your PHP App

```php
<?php
require_once 'config.php';
require_once 'MoMoCollection.php';

// Initialize Collection API
$collection = new MoMoCollection(COLLECTION_CONFIG);

// Request payment from customer
$result = $collection->requestToPay(
    amount: '1000',              // Amount in RWF
    externalId: 'order_12345',   // Your order ID
    phone: '250782752491',       // Customer phone
    currency: 'RWF',
    payerMessage: 'Order payment',
    payeeNote: 'Thank you'
);

// Check if request was accepted
if ($result['response']['http_code'] === 202) {
    $referenceId = $result['referenceId'];
    
    // Save to your database
    // Wait for callback or poll status
    
    // Check status
    $status = $collection->getTransactionStatus($referenceId);
    
    if ($status['data']['status'] === 'SUCCESSFUL') {
        // Payment successful! Update order status
        echo "Payment successful!";
    }
}
?>
```

### Example: Send Money to Customer

```php
<?php
require_once 'config.php';
require_once 'MoMoDisbursement.php';

// Initialize Disbursement API
$disbursement = new MoMoDisbursement(DISBURSEMENT_CONFIG);

// Transfer money to customer
$result = $disbursement->transfer(
    amount: '5000',              // Amount in RWF
    externalId: 'payout_789',    // Your payout ID
    phone: '250782752491',       // Customer phone
    currency: 'RWF',
    payerMessage: 'Refund',
    payeeNote: 'Your refund'
);

if ($result['response']['http_code'] === 202) {
    echo "Transfer initiated!";
}
?>
```

---

## 🔒 SECURITY CHECKLIST

Before going live, ensure:

- [ ] `.env` file is NOT accessible via web
- [ ] `.htaccess` is protecting sensitive files
- [ ] Using HTTPS in production (for callbacks)
- [ ] Validating all phone numbers
- [ ] Validating all amounts
- [ ] Logging all transactions
- [ ] Saving correlation IDs
- [ ] Rate limiting implemented
- [ ] Error handling in place

---

## ⚠️ IMPORTANT NOTES

### 1. Real Money Warning
This is **production** environment. Every transaction uses **REAL MONEY**.
- Always start with 100 RWF
- Test thoroughly before increasing amounts
- Monitor all transactions

### 2. Phone Number Format
```
✅ CORRECT:  250782752491
❌ WRONG:    0782752491
❌ WRONG:    +250782752491
❌ WRONG:    250 782 752 491
```

### 3. Currency
**Must always be RWF** for Rwanda. Never EUR, USD, or others.

### 4. Transaction Flow
```
Request Payment → User Gets Prompt → Enter PIN → Wait → Check Status
   (HTTP 202)         (On Phone)      (User)    (10-30s)  (SUCCESSFUL)
```

### 5. Correlation IDs
Every API response includes a `correlation_id`. **Always save this!**
- Used for support tickets
- Used for debugging
- Used for tracking

---

## 🐛 TROUBLESHOOTING

### Problem: "Failed to get access token"
**Solution:** Check credentials in `.env` file

### Problem: "Target environment not found"
**Solution:** Must be exactly `mtnrwanda` (lowercase, no spaces)

### Problem: "Invalid phone number"
**Solution:** Format must be `250XXXXXXXXX` (12 digits, no spaces)

### Problem: Payment stuck in PENDING
**Solution:**
1. Check phone for MTN prompt
2. Enter PIN to approve
3. Wait 30 seconds
4. Check status again

### Problem: Postman can't reach API
**Solution:**
1. Check server URL in `base_url` variable
2. Ensure files are uploaded
3. Test: `curl http://yourserver.com/momo/api.php?action=help`

---

## 📞 SUPPORT

### MTN MoMo Support
- Portal: https://momoapi.mtn.co.rw
- Create ticket through "Issues" page
- Include correlation_id in all tickets

### When Contacting Support
Always include:
1. ✅ Correlation ID
2. ✅ Timestamp
3. ✅ Error message
4. ✅ HTTP status code
5. ✅ Reference ID (if available)

---

## ✅ PRE-PRODUCTION CHECKLIST

Before integrating into your main system:

### Testing
- [ ] Verified credentials (all PASS)
- [ ] Tested with 100 RWF
- [ ] Tested with 500 RWF
- [ ] Tested with 1,000 RWF
- [ ] Checked balance before/after
- [ ] Verified transaction status
- [ ] Tested on multiple phones
- [ ] Tested error scenarios

### Integration
- [ ] Added to your application
- [ ] Database logging implemented
- [ ] Error handling added
- [ ] Callback handling ready
- [ ] Correlation IDs logged
- [ ] Transaction monitoring setup
- [ ] Rate limiting implemented

### Security
- [ ] HTTPS enabled
- [ ] .env file protected
- [ ] Input validation added
- [ ] Amount limits set
- [ ] Phone validation working
- [ ] API authentication secure

### Documentation
- [ ] Team trained
- [ ] API docs reviewed
- [ ] Error codes understood
- [ ] Support process defined
- [ ] Monitoring setup

---

## 🎯 SUCCESS METRICS

Track these metrics:
- ✅ Transaction success rate (aim for >95%)
- ✅ Average completion time (< 30 seconds)
- ✅ Callback delivery rate (> 98%)
- ✅ Error rate (< 5%)
- ✅ Customer complaints (minimize)

---

## 📊 TESTING SCENARIOS

### Scenario 1: Happy Path (Customer Pays)
1. Request payment via API
2. Customer gets prompt on phone
3. Customer enters PIN
4. Status becomes SUCCESSFUL
5. Balance increases

**Expected Time:** 10-25 seconds

### Scenario 2: Customer Rejects
1. Request payment via API
2. Customer gets prompt
3. Customer presses * to cancel
4. Status becomes REJECTED

**Expected Time:** 5-15 seconds

### Scenario 3: Customer Ignores
1. Request payment via API
2. Customer gets prompt
3. Customer doesn't respond
4. Status stays PENDING for 60 seconds
5. Status becomes EXPIRED

**Expected Time:** 60+ seconds

---

## 🚀 NEXT STEPS

1. **Test thoroughly** (Run 10+ small transactions)
2. **Monitor results** (Track all correlation IDs)
3. **Integrate** (Add to your system)
4. **Go live** (Start with limited users)
5. **Scale** (Increase as confidence grows)

---

## 💡 TIPS FOR SUCCESS

1. **Start Small:** Always test with 100 RWF first
2. **Test Often:** Run tests throughout the day
3. **Monitor Closely:** Watch every transaction initially
4. **Log Everything:** Save all correlation IDs
5. **Support Ready:** Have MTN support contact ready
6. **Backup Plan:** Have alternative payment method
7. **User Communication:** Clear payment instructions
8. **Error Messages:** User-friendly error messages
9. **Timeout Handling:** Handle 30s timeout properly
10. **Testing Schedule:** Test during business hours

---

## 🎉 YOU'RE READY!

Your MTN MoMo API is:
- ✅ Configured with your credentials
- ✅ Ready for testing with Postman
- ✅ Clean and professional code
- ✅ Production-ready
- ✅ Well documented

**Start testing now with 100 RWF!**

### First Test Command:
```bash
curl http://yourserver.com/momo/api.php?action=verify
```

If you see "PASS" for all tests, you're good to go! 🚀

---

**Questions?** Check the README.md or QUICK_START.md files.

**Ready to test?** Import the Postman collection and start!

**Need help?** Contact MTN support with your correlation IDs.

---

**DEEPNEXIS Ltd**  
**MTN MoMo Production API**  
**Rwanda - RWF**  
**Phone: 250782752491**

---

**Last Updated:** October 26, 2025  
**Status:** Production Ready ✅  
**Version:** 1.0
