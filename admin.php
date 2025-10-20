<?php 
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy(); // destroy all session data
    header("Location: login.php"); // redirect to login page
    exit;
}

session_start();

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

// User Class
class User {
    private $conn;
    private $table = "users";

    public $user_id;
    public $full_name;
    public $email;
    public $phone_number;
    public $username;
    public $password;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllUsers() {
        $query = "SELECT user_id, full_name, email, phone_number, username, created_at 
                  FROM " . $this->table . " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function getUserCount() {
        $query = "SELECT COUNT(*) as total FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    public function deleteUser() {
        $query = "DELETE FROM " . $this->table . " WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $this->user_id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }
}

// Order Class
class Order {
    private $conn;
    private $table = "orders";

    public $order_id;
    public $user_id;
    public $razorpay_order_id;
    public $razorpay_payment_id;
    public $razorpay_signature;
    public $total_amount;
    public $status;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllOrders() {
        $query = "SELECT o.*, u.full_name, u.email, u.username 
                  FROM " . $this->table . " o
                  LEFT JOIN users u ON o.user_id = u.user_id
                  ORDER BY o.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function getOrderCount() {
        $query = "SELECT COUNT(*) as total FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    public function getTotalRevenue() {
        $query = "SELECT SUM(total_amount) as revenue FROM " . $this->table . " WHERE status = 'paid'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['revenue'] ?? 0;
    }

    public function deleteOrder() {
        $query = "DELETE FROM " . $this->table . " WHERE order_id = :order_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":order_id", $this->order_id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }
}

// Product Class
class Product {
    private $conn;
    private $table = "products";

    public $product_id;
    public $product_name;
    public $category;
    public $description;
    public $price;
    public $stock_quantity;
    public $image_url;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllProducts() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function addProduct() {
        $query = "INSERT INTO " . $this->table . " 
                  (product_name, category, description, price, stock_quantity, image_url) 
                  VALUES (:product_name, :category, :description, :price, :stock_quantity, :image_url)";
        
        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->product_name = htmlspecialchars(strip_tags($this->product_name));
        $this->category = htmlspecialchars(strip_tags($this->category));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->price = htmlspecialchars(strip_tags($this->price));
        $this->stock_quantity = htmlspecialchars(strip_tags($this->stock_quantity));
        $this->image_url = htmlspecialchars(strip_tags($this->image_url));

        // Bind values
        $stmt->bindParam(":product_name", $this->product_name);
        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":price", $this->price);
        $stmt->bindParam(":stock_quantity", $this->stock_quantity);
        $stmt->bindParam(":image_url", $this->image_url);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function getProductCount() {
        $query = "SELECT COUNT(*) as total FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    public function deleteProduct() {
        $query = "DELETE FROM " . $this->table . " WHERE product_id = :product_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":product_id", $this->product_id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }
}

// Initialize Database
$database = new Database();
$db = $database->getConnection();

// Handle Product Addition
$message = '';
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $product = new Product($db);
    $product->product_name = $_POST['product_name'];
    $product->category = $_POST['category'];
    $product->description = $_POST['description'];
    $product->price = $_POST['price'];
    $product->stock_quantity = $_POST['stock_quantity'];

    // ---- begin: handle uploaded image file (new) ----
    $uploadedPath = ''; // default empty (will use placeholder if empty)
    if (!empty($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['image_file'];

        // basic validation
        $allowedTypes = ['image/jpeg','image/jpg','image/png','image/webp'];
        $maxSize = 2 * 1024 * 1024; // 2 MB

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $message = '<div class="alert error">Upload error. Please try again.</div>';
        } elseif ($file['size'] > $maxSize) {
            $message = '<div class="alert error">Image too large. Max 2MB.</div>';
        } elseif (!in_array(mime_content_type($file['tmp_name']), $allowedTypes, true)) {
            $message = '<div class="alert error">Invalid image type. Use JPG, PNG or WEBP.</div>';
        } else {
            // ensure uploads directory exists
            $uploadDir = __DIR__ . '/uploads/products';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // sanitize and generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safeBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
            $unique = time() . '_' . bin2hex(random_bytes(6));
            $filename = $safeBase . '_' . $unique . '.' . $ext;

            $dest = $uploadDir . '/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                // store path relative to web root (adjust if your web root differs)
                $uploadedPath = 'uploads/products/' . $filename;
            } else {
                $message = '<div class="alert error">Failed to save uploaded image.</div>';
            }
        }
    }

    // set image_url to uploaded path (or empty string)
    $product->image_url = $uploadedPath;

    // if there was no earlier error message from upload, attempt DB insert
    if ($message === '') {
        if($product->addProduct()) {
            $message = '<div class="alert success">Product added successfully!</div>';
        } else {
            $message = '<div class="alert error">Failed to add product.</div>';
        }
    }
}

// Handle User Deletion
if(isset($_GET['delete_user_id'])) {
    $user = new User($db);
    $user->user_id = $_GET['delete_user_id'];
    if($user->deleteUser()) {
        $message = '<div class="alert success">User deleted successfully!</div>';
    } else {
        $message = '<div class="alert error">Failed to delete user.</div>';
    }
}

// Handle Product Deletion
if(isset($_GET['delete_product_id'])) {
    $product = new Product($db);
    $product->product_id = $_GET['delete_product_id'];
    if($product->deleteProduct()) {
        $message = '<div class="alert success">Product deleted successfully!</div>';
    } else {
        $message = '<div class="alert error">Failed to delete product.</div>';
    }
}

// Handle Order Deletion
if(isset($_GET['delete_order_id'])) {
    $order = new Order($db);
    $order->order_id = $_GET['delete_order_id'];
    if($order->deleteOrder()) {
        $message = '<div class="alert success">Order deleted successfully!</div>';
    } else {
        $message = '<div class="alert error">Failed to delete order.</div>';
    }
}

// Get current page
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Get statistics
$user = new User($db);
$product = new Product($db);
$order = new Order($db);
$totalUsers = $user->getUserCount();
$totalProducts = $product->getProductCount();
$totalOrders = $order->getOrderCount();
$totalRevenue = $order->getTotalRevenue();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Store Admin Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
        }

        .sidebar-header {
            padding: 30px 25px;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            color: #ffffff;
            font-size: 28px;
            margin-bottom: 5px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-header p {
            color: #a8c5e8;
            font-size: 14px;
            margin-top: 5px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar-menu li {
            margin-bottom: 8px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 16px 25px;
            color: #e3f2fd;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 16px;
            border-left: 4px solid transparent;
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: #64b5f6;
            padding-left: 30px;
        }

        .sidebar-menu a.active {
            background: linear-gradient(90deg, rgba(100, 181, 246, 0.3) 0%, transparent 100%);
            border-left-color: #64b5f6;
            font-weight: 600;
        }

        .sidebar-menu a svg {
            width: 24px;
            height: 24px;
            margin-right: 15px;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 30px;
        }

        .header {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(33, 150, 243, 0.3);
            color: white;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .header p {
            color: #e3f2fd;
            font-size: 16px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(33, 150, 243, 0.3);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #2196F3 0%, #64b5f6 100%);
        }

        .stat-card h3 {
            color: #5e7c99;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card .number {
            font-size: 42px;
            font-weight: bold;
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card .icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 60px;
            opacity: 0.1;
        }

        /* Form Container */
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .form-container h2 {
            color: #1976D2;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #2196F3;
            font-size: 24px;
            font-weight: 700;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            color: #37474f;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 16px;
            border: 2px solid #e3f2fd;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2196F3;
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-group-full {
            grid-column: 1 / -1;
        }

        /* Table Container */
        .table-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        .table-container h2 {
            color: #1976D2;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #2196F3;
            font-size: 24px;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th,
        table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #e3f2fd;
        }

        table th {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        table tbody tr {
            transition: all 0.3s ease;
        }

        table tbody tr:hover {
            background: #e3f2fd;
            transform: scale(1.01);
        }

        table td {
            color: #37474f;
            font-size: 15px;
        }

        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(33, 150, 243, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            color: white;
            padding: 10px 20px;
            font-size: 14px;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(244, 67, 54, 0.4);
        }

        .alert {
            padding: 18px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert.success {
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }

        .alert.error {
            background: linear-gradient(135deg, #f44336 0%, #e53935 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(244, 67, 54, 0.3);
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
        }

        .welcome-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .welcome-card h2 {
            color: #1976D2;
            font-size: 28px;
            margin-bottom: 15px;
        }

        .welcome-card p {
            color: #546e7a;
            font-size: 16px;
            line-height: 1.6;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #78909c;
            font-size: 16px;
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #e3f2fd;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #2196F3 0%, #1976D2 100%);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #1976D2;
        }

        /* small logout button inside header */
        .btn-logout {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.12);
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            float: right;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-logout:hover {
            background: rgba(255,255,255,0.18);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>🐾 PetStore</h2>
                <p>Admin Dashboard</p>
            </div>
            <ul class="sidebar-menu">
                <li>
                    <a href="?page=dashboard" class="<?php echo $page == 'dashboard' ? 'active' : ''; ?>">
                        <svg fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                        </svg>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="?page=users" class="<?php echo $page == 'users' ? 'active' : ''; ?>">
                        <svg fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                        </svg>
                        Users
                    </a>
                </li>
                <li>
                    <a href="?page=products" class="<?php echo $page == 'products' ? 'active' : ''; ?>">
                        <svg fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3zM16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/>
                        </svg>
                        Products
                    </a>
                </li>
                <li>
                    <a href="?page=orders" class="<?php echo $page == 'orders' ? 'active' : ''; ?>">
                        <svg fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                        </svg>
                        Orders
                    </a>
                </li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <?php if($page == 'dashboard'): ?>
                <div class="header">
                    <!-- Logout button -->
                    <a href="?action=logout" class="btn-logout" title="Logout">Logout</a>
                    <h1>Dashboard Overview</h1>
                    <p>Welcome back to your admin panel</p>
                </div>

                <?php echo $message; ?>

                <!-- Dashboard Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Users</h3>
                        <div class="number"><?php echo $totalUsers; ?></div>
                        <div class="icon">👥</div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Products</h3>
                        <div class="number"><?php echo $totalProducts; ?></div>
                        <div class="icon">📦</div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Orders</h3>
                        <div class="number"><?php echo $totalOrders; ?></div>
                        <div class="icon">📋</div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Revenue</h3>
                        <div class="number">$<?php echo number_format($totalRevenue, 2); ?></div>
                        <div class="icon">💰</div>
                    </div>
                </div>

                <div class="welcome-card">
                    <h2>🎯 Welcome to Pet Store Admin</h2>
                    <p>Manage your pet supplies store efficiently. Use the sidebar to navigate between Users, Products, and Orders sections.</p>
                </div>

            <?php elseif($page == 'users'): ?>
                <div class="header">
                    <h1>User Management</h1>
                    <p>View and manage all registered users</p>
                </div>

                <?php echo $message; ?>

                <!-- Users List -->
                <div class="table-container">
                    <h2>📋 All Users</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone Number</th>
                                <th>Joined Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $users = $user->getAllUsers();
                            if($users->rowCount() > 0) {
                                while($row = $users->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<tr>";
                                    echo "<td><span class='badge'>#" . $row['user_id'] . "</span></td>";
                                    echo "<td><strong>" . htmlspecialchars($row['full_name']) . "</strong></td>";
                                    echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['phone_number'] ?: 'N/A') . "</td>";
                                    echo "<td>" . date('M d, Y - h:i A', strtotime($row['created_at'])) . "</td>";
                                    echo "<td><a href='?page=users&delete_user_id=" . $row['user_id'] . "' class='btn btn-danger' onclick='return confirm(\"Are you sure you want to delete this user?\")'>Delete</a></td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' class='no-data'>No users found in the database</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif($page == 'products'): ?>
                <div class="header">
                    <h1>Product Management</h1>
                    <p>Add and manage your pet store products</p>
                </div>

                <?php echo $message; ?>

                <!-- Add Product Form -->
                <div class="form-container">
                    <h2>➕ Add New Product</h2>
                    <!-- add enctype to support file uploads -->
                    <form method="POST" action="?page=products" enctype="multipart/form-data">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Product Name *</label>
                                <input type="text" name="product_name" placeholder="Enter product name" required>
                            </div>
                            <div class="form-group">
                                <label>Category *</label>
                                <select name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="Dog Food">Dog Food</option>
                                    <option value="Cat Food">Cat Food</option>
                                    <option value="Bird Food">Bird Food</option>
                                    <option value="Fish Food">Fish Food</option>
                                    <option value="Toys">Toys</option>
                                    <option value="Accessories">Accessories</option>
                                    <option value="Healthcare">Healthcare</option>
                                    <option value="Grooming">Grooming</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Price ($) *</label>
                                <input type="number" step="0.01" min="0" name="price" placeholder="0.00" required>
                            </div>
                            <div class="form-group">
                                <label>Stock Quantity *</label>
                                <input type="number" min="0" name="stock_quantity" placeholder="0" required>
                            </div>
                            <div class="form-group form-group-full">
                                <label>Product Image (optional) — JPG, PNG, WEBP, max 2MB</label>
                                <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp">
                            </div>
                            <div class="form-group form-group-full">
                                <label>Description</label>
                                <textarea name="description" placeholder="Enter product description..." rows="4"></textarea>
                            </div>
                        </div>
                        <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                    </form>
                </div>

                <!-- Products List -->
                <div class="table-container">
                    <h2>📦 All Products</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $products = $product->getAllProducts();
                            if($products->rowCount() > 0) {
                                while($row = $products->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<tr>";
                                    echo "<td><span class='badge'>#" . $row['product_id'] . "</span></td>";
                                    echo "<td><img src='" . ($row['image_url'] ?: 'https://via.placeholder.com/60') . "' alt='Product' class='product-image'></td>";
                                    echo "<td><strong>" . htmlspecialchars($row['product_name']) . "</strong></td>";
                                    echo "<td>" . htmlspecialchars($row['category']) . "</td>";
                                    echo "<td>$" . number_format($row['price'], 2) . "</td>";
                                    echo "<td>" . $row['stock_quantity'] . "</td>";
                                    echo "<td>" . date('M d, Y', strtotime($row['created_at'])) . "</td>";
                                    echo "<td><a href='?page=products&delete_product_id=" . $row['product_id'] . "' class='btn btn-danger' onclick='return confirm(\"Are you sure you want to delete this product?\")'>Delete</a></td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='8' class='no-data'>No products found. Add your first product above!</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif($page == 'orders'): ?>
                <div class="header">
                    <h1>Order Management</h1>
                    <p>View and manage all customer orders</p>
                </div>

                <?php echo $message; ?>

                <!-- Orders List -->
                <div class="table-container">
                    <h2>📋 All Orders</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Razorpay Order ID</th>
                                <th>Payment ID</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Order Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $orders = $order->getAllOrders();
                            if($orders->rowCount() > 0) {
                                while($row = $orders->fetch(PDO::FETCH_ASSOC)) {
                                    // Status badge color
                                    $statusColor = '';
                                    if($row['status'] == 'paid') {
                                        $statusColor = 'background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);';
                                    } elseif($row['status'] == 'failed') {
                                        $statusColor = 'background: linear-gradient(135deg, #f44336 0%, #e53935 100%);';
                                    } else {
                                        $statusColor = 'background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);';
                                    }
                                    
                                    echo "<tr>";
                                    echo "<td><span class='badge'>#" . $row['order_id'] . "</span></td>";
                                    echo "<td><strong>" . htmlspecialchars($row['full_name'] ?: 'N/A') . "</strong><br><small>@" . htmlspecialchars($row['username'] ?: 'N/A') . "</small></td>";
                                    echo "<td>" . htmlspecialchars($row['email'] ?: 'N/A') . "</td>";
                                    echo "<td><small>" . htmlspecialchars($row['razorpay_order_id']) . "</small></td>";
                                    echo "<td><small>" . htmlspecialchars($row['razorpay_payment_id'] ?: 'Pending') . "</small></td>";
                                    echo "<td><strong>$" . number_format($row['total_amount'], 2) . "</strong></td>";
                                    echo "<td><span class='badge' style='" . $statusColor . "'>" . strtoupper($row['status']) . "</span></td>";
                                    echo "<td>" . date('M d, Y - h:i A', strtotime($row['created_at'])) . "</td>";
                                    echo "<td><a href='?page=orders&delete_order_id=" . $row['order_id'] . "' class='btn btn-danger' onclick='return confirm(\"Are you sure you want to delete this order?\")'>Delete</a></td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='9' class='no-data'>No orders found in the database</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>