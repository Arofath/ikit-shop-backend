# Dashboard | KHQRPay

> Source: https://khqr.cc/docs/qr-payment
> Retrieved: 2026-08-04T03:52:34.821Z

# QR Payment Page

Generate secure, mobile-optimized checkout sessions for your customers using any Cambodian bank app.

DIRECT API v1.1.0

#### Dynamic Checkout Sessions

KHQRPay provides a managed checkout environment. Redirect your customers to the following endpoint with signed parameters to initiate a payment flow.

```http
GET https://khqr.cc/api/payment/request/oyzXeJoSP28s2qhCPbb8WFCExMwSa7cu
```

##### Request Parameters

| Key | Type | Required | Notes |
| --- | --- | --- | --- |
| `transaction_id` | string | YES | Your internal unique Order ID. |
| `amount` | decimal | YES | USD amount (Min: 0.01). |
| `success_url` | url | YES | Callback destination. |
| `items` | string | NO | What was bought. Returned unchanged in the callback. **Base64-encode** it if it contains JSON or non-ASCII text. |
| `custom_fields` | string | NO | Your own data to get back in the callback (e.g. a user ID). **Base64-encode** JSON. |
| `cancel_url` | url | NO | Where to send the customer if they abandon checkout. Stored on the transaction and readable via the API; leave it out and nothing is stored. |
| `hash` | sha1 | YES | sha1(secret + id + amt + url + remark) |

##### Implementation (PHP)

```
// 1. CONFIGURATION
$gateway_url = "https://khqr.cc/api/payment/request";
$profile_id  = "oyzXeJoSP28s2qhCPbb8WFCExMwSa7cu";
$secret_key  = "YOUR_SECRET_KEY";

// 2. TRANSACTION DETAILS
$payment_data = [
    "transaction_id" => "ORD_" . time(),
    "amount"         => 12.50,
    "success_url"    => "https://site.com/done",
    "remark"         => "Web Order #88"
];

// 3. SECURITY (HASHING)
$raw_string = $secret_key 
            . $payment_data['transaction_id'] 
            . $payment_data['amount'] 
            . $payment_data['success_url'] 
            . $payment_data['remark'];

$payment_data['hash'] = sha1($raw_string);

// 4. REDIRECT
$final_url = $gateway_url . "/" . $profile_id . "?" . http_build_query($payment_data);

header("Location: " . $final_url);
exit;
```

##### Implementation (HTML / JS Checkout Plugin)

Instead of redirecting customers away, you can load the premium checkout directly within a responsive modal or bottom-sheet using our client-side JavaScript plugin.

```
<?php
// 1. CONFIGURATION
$gateway_url = "https://khqr.cc/api/payment/request";
$profile_id  = "oyzXeJoSP28s2qhCPbb8WFCExMwSa7cu";
$secret_key  = "YOUR_SECRET_KEY";

// 2. TRANSACTION DETAILS
$payment_data = [
    "transaction_id" => "ORD_" . time(),
    "amount"         => 10.00,
    "success_url"    => "https://yoursite.com/success",
    "remark"         => "Order #88"
];

// 3. SECURITY HASH
$payment_data['hash'] = sha1(
    $secret_key
    . $payment_data['transaction_id']
    . $payment_data['amount']
    . $payment_data['success_url']
    . $payment_data['remark']
);

// 4. GENERATE URL FOR JS PLUGIN
$checkout_url = $gateway_url . "/" . $profile_id . "?" . http_build_query($payment_data);
?>

<!-- 1. Include the Checkout Plugin Script -->
<script src="https://khqr.cc/khqrcc-plugin.js"></script>

<!-- 2. Trigger the Iframe Modal using JavaScript -->
<script>
function payWithKhqr() {
    // Pass the server-side generated URL to the JavaScript plugin
    const checkoutUrl = "<?php echo $checkout_url; ?>";
    
    KhqrPayway.openCheckout({
        checkout_url: checkoutUrl,
        onSuccess: function(response) {
            console.log("Payment successful!", response);
            alert("Thank you! Payment received successfully.");
            window.location.href = "/success-page";
        },
        onError: function(error) {
            console.error("Payment modal closed or failed", error);
        }
    });
}
</script>

<!-- 3. Create your custom pay button -->
<button onclick="payWithKhqr()">Pay with KHQR</button>
```

##### WEBHOOK Callback / Server Notification

After the customer pays, our gateway sends a signed **HTTP POST** to your global Webhook URL (configured in Settings) or to the`success_url`you specified. This is how you fulfil orders automatically on your server.

Customer sees Redirected to`success_url`with`success_hash`,`success_time`, and`success_amount`query params. Your server receives Signed`POST application/json`callback with`transaction_id`,`amount`,`status`, and`hash`.

**Hash formula:**`sha256(secret + req_time + transaction_id + amount + "SUCCESS")`Always verify this on your server before fulfilling any order.

Full Callback Documentation Examples, hash verification, game top-up walkthrough

##### Test Environment

Simulate a real checkout flow using your current merchant profile settings.

Preview Button Launch Checkout (Direct)

Ensure the **Payment Secret** from your settings is kept private. Never expose it in client-side code.

Callback Docs Success URL Docs
