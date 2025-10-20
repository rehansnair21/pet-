<?php
session_start();

// ===============================
//  CLASS DEFINITIONS
// ===============================

// Database Connection Class
class Database {
    private $host = "localhost";
    private $user = "root";
    private $pass = "";
    private $dbname = "pet";
    public $conn;

    public function __construct() {
        $this->connectDB();
    }

    private function connectDB() {
        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->dbname);
        if ($this->conn->connect_error) {
            die("Database Connection Failed: " . $this->conn->connect_error);
        }
    }
}

// User Authentication Class
class Auth {
    private $db;

    public function __construct($database) {
        $this->db = $database->conn;
    }

    public function login($email, $password) {
        // ===============================
        // 🔒 1. ADMIN DIRECT LOGIN
        // ===============================
        if ($email === 'admin@petstore.com' && $password === 'admin123') {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['email'] = $email;
            header("Location: admin.php");
            exit;
        }

        // ===============================
        // 2. REGULAR USER LOGIN
        // ===============================
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                // Check if user is admin from DB (optional)
                if (isset($user['role']) && $user['role'] === 'admin') {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['email'] = $user['email'];
                    header("Location: admin.php");
                    exit;
                }

                // Normal user login
                $_SESSION['user_logged_in'] = true;
    $_SESSION['user_id'] = $user['user_id']; // <-- ADD THIS LINE
    $_SESSION['email'] = $user['email'];
    $_SESSION['username'] = $user['username'];
    header("Location: home.php");
    exit;
            } else {
                return "❌ Invalid password.";
            }
        } else {
            return "❌ Email not found.";
        }
    }
}

// ===============================
//  FORM SUBMISSION HANDLER
// ===============================
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $auth = new Auth($db);

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $message = "⚠️ Please enter both email and password.";
    } else {
        $message = $auth->login($email, $password);
    }
}
?>

<!-- ===============================
     HTML + CSS FORM SECTION
     =============================== -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pet Supplies Login</title>
  <style>
    :root {
      --blue-500: #0b5fd7;
      --blue-600: #0748b7;
      --muted: #4b6b8a;
      --danger: #b00020;
    }

    html, body {
      height: 100%;
      margin: 0;
      font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
      background: url('pet.jpg') no-repeat center center fixed;
      background-size: cover;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    form {
      background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(233,243,255,0.9));
      padding: 34px;
      border-radius: 14px;
      box-shadow: 0 12px 36px rgba(7,72,183,0.08);
      width: 380px;
      border: 1px solid rgba(11,95,215,0.08);
    }

    h2 {
      text-align: center;
      margin: 0 0 18px;
      color: var(--blue-500);
      font-weight: 700;
    }

    .field { margin-bottom: 14px; }
    label { display: block; margin-bottom: 6px; color: var(--muted); font-weight: 600; }
    input {
      width: 100%;
      padding: 11px;
      border: 1px solid rgba(11,95,215,0.12);
      border-radius: 8px;
      background: #fff;
      font-size: 0.95rem;
    }
    input:focus {
      outline: none;
      border-color: var(--blue-500);
      box-shadow: 0 6px 18px rgba(11,95,215,0.06);
    }

    button {
      background: linear-gradient(180deg, var(--blue-500), var(--blue-600));
      color: #fff;
      border: none;
      padding: 11px;
      border-radius: 10px;
      width: 100%;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 6px 18px rgba(11,95,215,0.12);
      transition: transform .08s ease;
    }
    button:hover { transform: translateY(-1px); }

    .message {
      text-align: center;
      color: var(--danger);
      margin-bottom: 12px;
      font-weight: 700;
    }

    .register-link {
      text-align: center;
      margin-top: 10px;
      color: var(--muted);
    }
    .register-link a {
      color: var(--blue-500);
      font-weight: 700;
      text-decoration: none;
    }
    .register-link a:hover { text-decoration: underline; }
  </style>
</head>
<body>

  <form method="POST" action="">
    <h2>Login</h2>

    <?php if (!empty($message)): ?>
      <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="field">
      <label for="email">Email</label>
      <input id="email" type="email" name="email" required>
    </div>

    <div class="field">
      <label for="password">Password</label>
      <input id="password" type="password" name="password" required>
    </div>

    <button type="submit">Login</button>

    <div class="register-link">
      Dont have an account? <a href="reg.php">Register here</a>
    </div>
  </form>

</body>
</html>
