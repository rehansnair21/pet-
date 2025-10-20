<?php
session_start();
require_once 'config.php';

// If user is not logged in, or cart is empty, redirect them
if (!isset($_SESSION['user_logged_in']) || empty($_SESSION['cart'])) {
    // Save the intended page and redirect to login
    $_SESSION['redirect_url'] = 'checkout.php';
    header('Location: login.php');
    exit;
}

// Calculate total
$total = 0;
foreach ($_SESSION['cart'] as $item) {
    $total += $item['price'] * $item['quantity'];
}
// Adding a fixed tax for this example
$tax = $total * 0.08;
$grandTotal = $total + $tax;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout - PetStore</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background: #f5f5f5; }
        .container { max-width: 600px; margin-top: 50px; }
        .card { box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-radius: 10px; }
        .card-header { border-top-left-radius: 10px; border-top-right-radius: 10px;}
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="m-0">Order Summary</h3>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php foreach ($_SESSION['cart'] as $item): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?php echo htmlspecialchars($item['product_name']); ?> (x<?php echo $item['quantity']; ?>)</span>
                        <span>₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                    </li>
                    <?php endforeach; ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Subtotal</span>
                        <span>₹<?php echo number_format($total, 2); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Tax (8%)</span>
                        <span>₹<?php echo number_format($tax, 2); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between font-weight-bold h5">
                        <span>Grand Total</span>
                        <span>₹<?php echo number_format($grandTotal, 2); ?></span>
                    </li>
                </ul>
            </div>
            <div class="card-footer text-center">
                <button id="rzp-button1" class="btn btn-success btn-lg">Pay with Razorpay</button>
            </div>
        </div>
    </div>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

    <script>
    $(document).ready(function() {
        $('#rzp-button1').on('click', function(e) {
            e.preventDefault();

            var totalAmount = <?php echo round($grandTotal, 2); ?>; // in rupees (float)
            var amountPaise = Math.round(totalAmount * 100); // integer

            // 1) Create an order on the server and get order_id
            fetch('create_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ amount: amountPaise })
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (!data.id) {
                    alert('Could not create order. Please try again.');
                    console.error('Create order error:', data);
                    return;
                }

                var options = {
                    "key": "<?php echo RAZORPAY_KEY_ID; ?>",
                    "amount": amountPaise,
                    "currency": "INR",
                    "name": "PetStore",
                    "description": "Order Payment",
                    "image": "https://example.com/your_logo.png", // Optional: Add a logo URL
                    "order_id": data.id, // server created order id
                    "handler": function (response){
                        // Send payment info to server for verification
                        $.ajax({
                            url: 'verify_payment.php',
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_order_id: response.razorpay_order_id,
                                razorpay_signature: response.razorpay_signature,
                                totalAmount: totalAmount // Send the final amount in Rupees
                            },
                            success: function (resp) {
                               if (resp.status === 'success') {
                                   window.location.href = 'success.php';
                               } else {
                                   window.location.href = 'failure.php?error=' + encodeURIComponent(resp.message || 'Payment verification failed');
                               }
                            },
                            error: function (xhr, status, error) {
                                console.error("AJAX Error:", status, error, xhr.responseText);
                                window.location.href = 'failure.php?error=' + encodeURIComponent('Payment verification request failed. Check console for details.');
                            }
                        });
                    },
                    "prefill": {
                        "name": "<?php echo addslashes($_SESSION['username'] ?? 'Guest User'); ?>",
                        "email": "<?php echo addslashes($_SESSION['email'] ?? ''); ?>"
                    },
                    "theme": {
                        "color": "#2196F3"
                    }
                };
                var rzp1 = new Razorpay(options);
                rzp1.open();

                rzp1.on('payment.failed', function (response){
                    window.location.href = 'failure.php?error=' + encodeURIComponent(response.error.description);
                });
            })
            .catch(function(err) {
                console.error('Create order fetch error:', err);
                alert('Unable to start payment process. Please try again.');
            });
        });
    });
    </script>
</body>
</html>