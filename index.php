<?php
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

// Product Class
class Product {
    private $conn;
    private $table = "products";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllProducts() {
        $query = "SELECT * FROM " . $this->table . " WHERE stock_quantity > 0 ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function getProductsByCategory($category) {
        $query = "SELECT * FROM " . $this->table . " WHERE category = :category AND stock_quantity > 0 ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":category", $category);
        $stmt->execute();
        return $stmt;
    }

    public function getAllCategories() {
        $query = "SELECT DISTINCT category FROM " . $this->table . " ORDER BY category ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }
}

// Initialize Database
$database = new Database();
$db = $database->getConnection();
$product = new Product($db);

// Get filter category
$filterCategory = isset($_GET['category']) ? $_GET['category'] : 'all';

// Get products
if($filterCategory != 'all') {
    $products = $product->getProductsByCategory($filterCategory);
} else {
    $products = $product->getAllProducts();
}

// Get all categories
$categories = $product->getAllCategories();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PetStore - Your Pet's Best Friend</title>
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
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, rgba(33, 150, 243, 0.9), rgba(25, 118, 210, 0.9)),
                        url('https://images.unsplash.com/photo-1450778869180-41d0601e046e?w=1600') center/cover;
            padding: 100px 20px;
            text-align: center;
            color: white;
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
        }

        .hero h1 {
            font-size: 48px;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .hero p {
            font-size: 20px;
            margin-bottom: 30px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }

        .hero-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            max-width: 1000px;
            margin: 50px auto 0;
        }

        .hero-feature {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 15px;
            transition: transform 0.3s;
        }

        .hero-feature:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.25);
        }

        .hero-feature .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .hero-feature h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }

        /* Products Section */
        .products-section {
            padding: 80px 20px;
            background: #f5f5f5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-header h2 {
            font-size: 36px;
            color: #1976D2;
            margin-bottom: 15px;
        }

        .section-header p {
            font-size: 18px;
            color: #666;
        }

        /* Category Filter */
        .category-filter {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 12px 24px;
            border: 2px solid #2196F3;
            background: white;
            color: #2196F3;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: #2196F3;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(33, 150, 243, 0.3);
        }

        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }

        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 30px rgba(33, 150, 243, 0.3);
        }

        .product-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }

        .product-info {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .product-category {
            display: inline-block;
            padding: 5px 12px;
            background: #e3f2fd;
            color: #1976D2;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
            width: fit-content;
        }

        .product-name {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .product-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex-grow: 1;
        }

        .product-footer {
            display: flex;
            flex-direction: column;
            gap: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            margin-top: auto;
        }

        .product-price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .product-price {
            font-size: 24px;
            font-weight: bold;
            color: #2196F3;
        }

        .product-stock {
            font-size: 14px;
            color: #4caf50;
            font-weight: 600;
        }

        .btn-buy {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            width: 100%;
            display: block;
        }

        .btn-buy:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(33, 150, 243, 0.4);
        }

        /* Login Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 0;
            border-radius: 20px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            animation: slideDown 0.3s;
            overflow: hidden;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .modal-header h2 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .modal-header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .modal-icon {
            font-size: 60px;
            margin-bottom: 15px;
        }

        .modal-body {
            padding: 40px;
            text-align: center;
        }

        .modal-body p {
            font-size: 18px;
            color: #555;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn-login {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
            padding: 14px 35px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(33, 150, 243, 0.4);
        }

        .btn-cancel {
            background: #f5f5f5;
            color: #666;
            padding: 14px 35px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
        }

        .btn-cancel:hover {
            background: #e0e0e0;
        }

        .no-products {
            text-align: center;
            padding: 60px 20px;
            color: #666;
            grid-column: 1 / -1;
        }

        .no-products h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        /* About Section */
        .about-section {
            padding: 80px 20px;
            background: white;
        }

        .about-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: center;
        }

        .about-text h2 {
            font-size: 36px;
            color: #1976D2;
            margin-bottom: 20px;
        }

        .about-text p {
            font-size: 16px;
            color: #666;
            margin-bottom: 20px;
            line-height: 1.8;
        }

        .about-features {
            margin-top: 30px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .feature-icon {
            font-size: 32px;
        }

        .feature-text h4 {
            color: #333;
            margin-bottom: 5px;
        }

        .feature-text p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }

        .about-image {
            width: 100%;
            height: 500px;
            object-fit: cover;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        /* Footer */
        .footer {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 40px 20px 20px;
            text-align: center;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer h3 {
            font-size: 24px;
            margin-bottom: 20px;
        }

        .footer p {
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 20px 0;
        }

        .social-links a {
            color: white;
            font-size: 24px;
            transition: transform 0.3s;
        }

        .social-links a:hover {
            transform: scale(1.2);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 32px;
            }

            .nav-links {
                gap: 10px;
                font-size: 14px;
            }

            .about-content {
                grid-template-columns: 1fr;
            }

            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }

            .modal-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="#home" class="logo">🐾 PetStore</a>
            <ul class="nav-links">
                <li><a href="#home">Home</a></li>
                <li><a href="#products">Products</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a onclick="showLoginModal()">🛒 Cart (0)</a></li>
            </ul>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-content">
            <h1>🐾 Everything Your Pet Needs</h1>
            <p>Quality products, loving care, and expert advice for your furry, feathered, and finned friends</p>
            
            <div class="hero-features">
                <div class="hero-feature">
                    <div class="icon">🐕</div>
                    <h3>For Dogs</h3>
                    <p>Premium food, toys & accessories</p>
                </div>
                <div class="hero-feature">
                    <div class="icon">🐈</div>
                    <h3>For Cats</h3>
                    <p>Everything felines love</p>
                </div>
                <div class="hero-feature">
                    <div class="icon">🐦</div>
                    <h3>For Birds</h3>
                    <p>Seeds, cages & more</p>
                </div>
                <div class="hero-feature">
                    <div class="icon">🐠</div>
                    <h3>For Fish</h3>
                    <p>Aquarium supplies & food</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Products Section -->
    <section class="products-section" id="products">
        <div class="container">
            <div class="section-header">
                <h2>Our Products</h2>
                <p>Discover our wide range of quality pet supplies</p>
            </div>

            <!-- Category Filter -->
            <div class="category-filter">
                <a href="?category=all#products" class="filter-btn <?php echo $filterCategory == 'all' ? 'active' : ''; ?>">All Products</a>
                <?php
                $cats = $product->getAllCategories();
                while($cat = $cats->fetch(PDO::FETCH_ASSOC)) {
                    $isActive = $filterCategory == $cat['category'] ? 'active' : '';
                    echo '<a href="?category=' . urlencode($cat['category']) . '#products" class="filter-btn ' . $isActive . '">' . htmlspecialchars($cat['category']) . '</a>';
                }
                ?>
            </div>

            <!-- Product Grid -->
            <div class="product-grid">
                <?php
                if($products->rowCount() > 0) {
                    while($row = $products->fetch(PDO::FETCH_ASSOC)) {
                        $imageUrl = $row['image_url'] ?: 'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?w=400';
                        ?>
                        <div class="product-card">
                            <img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="<?php echo htmlspecialchars($row['product_name']); ?>" class="product-image">
                            <div class="product-info">
                                <span class="product-category"><?php echo htmlspecialchars($row['category']); ?></span>
                                <h3 class="product-name"><?php echo htmlspecialchars($row['product_name']); ?></h3>
                                <p class="product-description"><?php echo htmlspecialchars($row['description'] ?: 'Quality product for your beloved pet.'); ?></p>
                                <div class="product-footer">
                                    <div class="product-price-row">
                                        <div class="product-price">₹<?php echo number_format($row['price'], 2); ?></div>
                                        <div class="product-stock">Stock: <?php echo $row['stock_quantity']; ?></div>
                                    </div>
                                    <button onclick="showLoginModal()" class="btn-buy">Add to Cart</button>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo '<div class="no-products">';
                    echo '<h3>No products found</h3>';
                    echo '<p>Check back soon for new arrivals!</p>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about-section" id="about">
        <div class="container">
            <div class="about-content">
                <div class="about-text">
                    <h2>About PetStore</h2>
                    <p>Welcome to PetStore, your one-stop destination for all your pet care needs. We've been serving pet lovers for over 10 years, providing quality products and expert advice to help you give your pets the best life possible.</p>
                    <p>Our mission is simple: to make pet parenting easier, more affordable, and more enjoyable. Whether you have a dog, cat, bird, fish, or any other pet, we have everything you need to keep them happy and healthy.</p>
                    
                    <div class="about-features">
                        <div class="feature-item">
                            <div class="feature-icon">✓</div>
                            <div class="feature-text">
                                <h4>Quality Products</h4>
                                <p>Only the best brands and highest quality items</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">🚚</div>
                            <div class="feature-text">
                                <h4>Fast Delivery</h4>
                                <p>Quick and reliable shipping to your doorstep</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">💝</div>
                            <div class="feature-text">
                                <h4>Best Prices</h4>
                                <p>Competitive pricing and regular special offers</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">🎓</div>
                            <div class="feature-text">
                                <h4>Expert Advice</h4>
                                <p>Professional guidance from pet care specialists</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <img src="https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=600" alt="Happy pets" class="about-image">
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="footer-content">
            <h3>🐾 PetStore</h3>
            <p>📍 123 Pet Street, Animal City, PC 12345</p>
            <p>📞 +1 (555) 123-4567</p>
            <p>✉️ info@petstore.com</p>
            
            <div class="social-links">
                <a href="#" title="Facebook">📘</a>
                <a href="#" title="Instagram">📷</a>
                <a href="#" title="Twitter">🐦</a>
                <a href="#" title="YouTube">📺</a>
            </div>
            
            <p style="margin-top: 30px; opacity: 0.8;">&copy; 2025 PetStore. All rights reserved. Made with ❤️ for pets.</p>
        </div>
    </footer>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">🔒</div>
                <h2>Login Required</h2>
                <p>Please login to continue shopping</p>
            </div>
            <div class="modal-body">
                <p>You need to be logged in to add items to your cart and make purchases. Join our community of pet lovers today!</p>
                <div class="modal-buttons">
                    <a href="login.php" class="btn-login">Login / Register</a>
                    <button onclick="closeLoginModal()" class="btn-cancel">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showLoginModal() {
            document.getElementById('loginModal').style.display = 'block';
        }

        function closeLoginModal() {
            document.getElementById('loginModal').style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('loginModal');
            if (event.target == modal) {
                closeLoginModal();
            }
        }
    </script>
</body>
</html>