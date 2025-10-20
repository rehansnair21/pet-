<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

// Ensure user is logged in and has a cart
if (!isset($_SESSION['user_id']) || empty($_SESSION['cart'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized or empty cart.']);
    exit;
}

$payment_id = $_POST['razorpay_payment_id'] ?? '';
$order_id_razorpay = $_POST['razorpay_order_id'] ?? '';
$signature = $_POST['razorpay_signature'] ?? '';
$totalAmount = floatval($_POST['totalAmount'] ?? 0);

if (!$payment_id || !$order_id_razorpay || !$signature) {
    echo json_encode(['status' => 'error', 'message' => 'Missing payment parameters.']);
    exit;
}

// 1. Verify Razorpay Signature
$generated_signature = hash_hmac('sha256', $order_id_razorpay . '|' . $payment_id, RAZORPAY_KEY_SECRET);

if (!hash_equals($generated_signature, $signature)) {
    echo json_encode(['status' => 'error', 'message' => 'Signature verification failed.']);
    exit;
}

// 2. Signature is valid, proceed to save order to the database
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

// 3. Start a transaction
$db->begin_transaction();

try {
    // 4. Insert into 'orders' table
    $stmt_order = $db->prepare(
        "INSERT INTO orders (user_id, razorpay_order_id, razorpay_payment_id, razorpay_signature, total_amount, status) VALUES (?, ?, ?, ?, ?, 'paid')"
    );
    $user_id = $_SESSION['user_id'];
    $stmt_order->bind_param("isssd", $user_id, $order_id_razorpay, $payment_id, $signature, $totalAmount);
    $stmt_order->execute();

    // Get the ID of the order we just created
    $order_id_db = $db->insert_id;

    if ($order_id_db == 0) {
        throw new Exception("Failed to create order record.");
    }

    // Prepare statements for order items and stock update
    $stmt_items = $db->prepare(
        "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)"
    );
    $stmt_stock = $db->prepare(
        "UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ? AND stock_quantity >= ?"
    );

    // 5. Loop through cart items and insert into 'order_items', then update stock
    foreach ($_SESSION['cart'] as $pid => $item) {
        $product_id = intval($pid);
        $quantity = intval($item['quantity']);
        $price = floatval($item['price']);

        // Insert into order_items
        $stmt_items->bind_param("iiid", $order_id_db, $product_id, $quantity, $price);
        $stmt_items->execute();

        // Update product stock
        $stmt_stock->bind_param("iii", $quantity, $product_id, $quantity);
        $stmt_stock->execute();
        
        // Check if stock update was successful
        if ($stmt_stock->affected_rows == 0) {
            throw new Exception("Failed to update stock for product ID: $product_id. Insufficient stock.");
        }
    }

    // 6. If everything is successful, commit the transaction
    $db->commit();

    // 7. Clear the cart from the session
    unset($_SESSION['cart']);

    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    // 8. If any step fails, roll back the entire transaction
    $db->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Order processing failed: ' . $e->getMessage()]);
} finally {
    // Close statements and connection
    if (isset($stmt_order)) $stmt_order->close();
    if (isset($stmt_items)) $stmt_items->close();
    if (isset($stmt_stock)) $stmt_stock->close();
    $db->close();
}

exit;