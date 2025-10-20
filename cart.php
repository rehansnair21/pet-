<?php
session_start();

// Initialize cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Database Configuration
class Database {
    private $host = "localhost";
    private $db_name = "pet";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            echo "Connection error: " . $e->getMessage();
        }
        return $this->conn;
    }
}

// Product Class
class Product {
    private $conn;
    private $table = "products";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE product_id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Initialize Database
$database = new Database();
$db = $database->getConnection();
$product = new Product($db);

// Handle update quantity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quantity'])) {
    $pid = intval($_POST['product_id'] ?? 0);
    $qty = intval($_POST['quantity'] ?? 1);
    
    if ($pid > 0 && $qty > 0 && isset($_SESSION['cart'][$pid])) {
        // Check stock availability
        $p = $product->getById($pid);
        if ($p && $qty <= $p['stock_quantity']) {
            $_SESSION['cart'][$pid]['quantity'] = $qty;
            $message = "Quantity updated successfully!";
            $messageType = "success";
        } else {
            $message = "Cannot update quantity. Insufficient stock!";
            $messageType = "error";
        }
    }
    header('Location: cart.php');
    exit;
}

// Handle remove from cart
if (isset($_GET['remove'])) {
    $rid = intval($_GET['remove']);
    if ($rid > 0 && isset($_SESSION['cart'][$rid])) {
        unset($_SESSION['cart'][$rid]);
        $message = "Item removed from cart!";
        $messageType = "success";
    }
    header('Location: cart.php');
    exit;
}

// Handle clear cart
if (isset($_GET['clear']) && $_GET['clear'] == '1') {
    $_SESSION['cart'] = [];
    $message = "Cart cleared!";
    $messageType = "success";
    header('Location: cart.php');
    exit;
}

// Calculate totals
function calculateTotal() {
    $total = 0;
    foreach ($_SESSION['cart'] as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total;
}

function cartItemCount() {
    $count = 0;
    foreach ($_SESSION['cart'] as $item) {
        $count += $item['quantity'];
    }
    return $count;
}

$cartTotal = calculateTotal();
$itemCount = cartItemCount();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - PetStore</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
        }

        /* Navigation */
        .navbar {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 28px;
            font-weight: bold;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 20px;
            align-items: center;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            padding: 8px 16px;
            border-radius: 5px;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-size: 36px;
            color: #1976D2;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #666;
            font-size: 18px;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Cart Layout */
        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
            align-items: start;
        }

        /* Cart Items */
        .cart-items {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 30px;
        }

        .cart-items h2 {
            color: #1976D2;
            font-size: 24px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #2196F3;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 20px;
            align-items: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .cart-item:hover {
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .cart-item-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .cart-item-details {
            flex: 1;
        }

        .cart-item-name {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .cart-item-price {
            color: #2196F3;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .quantity-controls label {
            font-weight: 600;
            color: #666;
        }

        .quantity-input {
            width: 80px;
            padding: 8px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            text-align: center;
        }

        .btn-update {
            background: #2196F3;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-update:hover {
            background: #1976D2;
            transform: translateY(-1px);
        }

        .cart-item-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: flex-end;
        }

        .item-subtotal {
            font-size: 22px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .btn-remove {
            background: #e53935;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-remove:hover {
            background: #c62828;
            transform: translateY(-1px);
        }

        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-cart-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .empty-cart h3 {
            font-size: 24px;
            color: #333;
            margin-bottom: 15px;
        }

        .empty-cart p {
            color: #666;
            font-size: 16px;
            margin-bottom: 30px;
        }

        /* Cart Summary */
        .cart-summary {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 30px;
            position: sticky;
            top: 100px;
        }

        .cart-summary h3 {
            color: #1976D2;
            font-size: 22px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #2196F3;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            font-size: 16px;
            color: #666;
        }

        .summary-row.total {
            border-top: 2px solid #eee;
            margin-top: 15px;
            padding-top: 20px;
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }

        .summary-row.total .amount {
            color: #2196F3;
        }

        .btn-checkout {
            background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%);
            color: white;
            padding: 16px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 18px;
            width: 100%;
            margin-top: 20px;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .btn-checkout:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.4);
        }

        .btn-continue {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            width: 100%;
            margin-top: 10px;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .btn-continue:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(33, 150, 243, 0.4);
        }

        .btn-clear-cart {
            background: transparent;
            color: #e53935;
            padding: 12px;
            border: 2px solid #e53935;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            width: 100%;
            margin-top: 15px;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .btn-clear-cart:hover {
            background: #e53935;
            color: white;
        }

        /* Responsive */
        @media (max-width: 968px) {
            .cart-layout {
                grid-template-columns: 1fr;
            }

            .cart-summary {
                position: static;
            }

            .cart-item {
                grid-template-columns: 100px 1fr;
            }

            .cart-item-actions {
                grid-column: 1 / -1;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #ddd;
            }
        }

        @media (max-width: 576px) {
            .cart-item {
                grid-template-columns: 1fr;
            }

            .cart-item-image {
                width: 100%;
                height: 200px;
            }

            .quantity-controls {
                flex-wrap: wrap;
            }

            .nav-links {
                gap: 10px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="home.php#home" class="logo">🐾 PetStore</a>
            <ul class="nav-links">
                <li><a href="home.php#home">Home</a></li>
                <li><a href="home.php#products">Products</a></li>
                <li><a href="home.php#about">About</a></li>
                 <li><a href="cart.php">🛒 Cart (<?php echo $itemCount; ?>)</a></li>
             </ul>
         </div>
     </nav>

    <div class="container">
        <div class="page-header">
            <h1>🛒 Shopping Cart</h1>
            <p>Review your items and proceed to checkout</p>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($_SESSION['cart'])): ?>
            <!-- Empty Cart -->
            <div class="cart-items">
                <div class="empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <h3>Your cart is empty</h3>
                    <p>Looks like you haven't added any items to your cart yet.</p>
                    <a href="home.php#products" class="btn-checkout">Start Shopping</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Cart with Items -->
            <div class="cart-layout">
                <div class="cart-items">
                    <h2>Cart Items (<?php echo $itemCount; ?>)</h2>
                    
                    <?php foreach ($_SESSION['cart'] as $pid => $item): ?>
                        <div class="cart-item">
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="cart-item-image">
                            
                            <div class="cart-item-details">
                                <div class="cart-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <div class="cart-item-price">₹<?php echo number_format($item['price'], 2); ?> each</div>
                                
                                <form method="POST" class="quantity-controls">
                                    <input type="hidden" name="product_id" value="<?php echo $pid; ?>">
                                    <label>Quantity:</label>
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="99" class="quantity-input">
                                    <button type="submit" name="update_quantity" class="btn-update">Update</button>
                                </form>
                            </div>
                            
                            <div class="cart-item-actions">
                                <div class="item-subtotal">
                                    ₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                </div>
                                <a href="?remove=<?php echo $pid; ?>" class="btn-remove" onclick="return confirm('Remove this item from cart?')">Remove</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-summary">
                    <h3>Order Summary</h3>
                    
                    <div class="summary-row">
                        <span>Items (<?php echo $itemCount; ?>):</span>
                        <span>₹<?php echo number_format($cartTotal, 2); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Shipping:</span>
                        <span>FREE</span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Tax:</span>
                        <span>₹<?php echo number_format($cartTotal * 0.08, 2); ?></span>
                    </div>
                    
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span class="amount">₹<?php echo number_format($cartTotal * 1.08, 2); ?></span>
                    </div>
                    
                    <a href="checkout.php" id="btnCheckout" class="btn-checkout">Proceed to Checkout</a>
                    <a href="home.php#products" class="btn-continue">Continue Shopping</a>
                    <a href="?clear=1" class="btn-clear-cart" onclick="return confirm('Are you sure you want to clear your cart?')">Clear Cart</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // ensure the button always redirects to checkout.php
        (function(){
            var btn = document.getElementById('btnCheckout');
            if(btn){
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    window.location.href = 'checkout.php';
                });
            }
        })();
    </script>
</body>
</html>