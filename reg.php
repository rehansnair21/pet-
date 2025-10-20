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
    private $dbname = "pet"; // ✅ your database name
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

// User Registration Class
class User {
    private $db;

    public function __construct($database) {
        $this->db = $database->conn;
    }

    public function registerUser($full_name, $email, $phone_number, $username, $password) {
        $errors = [];

        // Trim inputs
        $full_name = trim($full_name ?? '');
        $email = trim($email ?? '');
        $phone_number = trim($phone_number ?? '');
        $username = trim($username ?? '');
        $password = trim($password ?? '');

        // Full name validation
        if ($full_name === '' || !preg_match("/^[\p{L} '\-]{2,100}$/u", $full_name)) {
            $errors[] = "Invalid full name.";
        }

        // Email validation
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address.";
        }

        // Phone validation
        if ($phone_number !== '' && !preg_match('/^[89][0-9]{9}$/', $phone_number)) {
            $errors[] = "Phone number must be 10 digits and start with 8 or 9.";
        }

        // Username validation
        if ($username === '' || !preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
            $errors[] = "Invalid username.";
        }

        // Password validation
        if ($password === '' || !preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password)) {
            $errors[] = "Weak password.";
        }

        if (!empty($errors)) {
            return ['success' => false, 'message' => implode(' ', $errors)];
        }

        try {
            // Check for duplicates
            $checkStmt = $this->db->prepare("SELECT user_id FROM users WHERE email = ? OR username = ?");
            $checkStmt->bind_param("ss", $email, $username);
            $checkStmt->execute();
            $checkStmt->store_result();
            if ($checkStmt->num_rows > 0) {
                $checkStmt->close();
                return ['success' => false, 'message' => "Email or Username already exists."];
            }
            $checkStmt->close();

            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            // Insert user
            $stmt = $this->db->prepare("INSERT INTO users (full_name, email, phone_number, username, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $full_name, $email, $phone_number, $username, $hashedPassword);
            $stmt->execute();
            $stmt->close();

            return ['success' => true, 'message' => "Registration successful."];
        } catch (Exception $e) {
            return ['success' => false, 'message' => "Error: " . $e->getMessage()];
        }
    }
}

// ===============================
//  FORM SUBMISSION HANDLER
// ===============================
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$old = [
    'full_name' => '',
    'email' => '',
    'phone_number' => '',
    'username' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $user = new User($db);

    $full_name   = $_POST['full_name']   ?? '';
    $email       = $_POST['email']       ?? '';
    $phone_number= $_POST['phone_number']?? '';
    $username    = $_POST['username']    ?? '';
    $password    = $_POST['password']    ?? '';

    // keep old values for repopulation on error
    $old['full_name'] = $full_name;
    $old['email'] = $email;
    $old['phone_number'] = $phone_number;
    $old['username'] = $username;

    $result = $user->registerUser($full_name, $email, $phone_number, $username, $password);

    if ($result['success']) {
        // on success, set flash and redirect to login.php
        $_SESSION['flash'] = $result;
        header('Location: login.php');
        exit;
    } else {
        // on error, show messages on the same page
        $flash = $result;
        // do not redirect so user stays on reg.php
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
  <title>Pet Supplies Registration</title>
  <style>
    /* Blue theme */
    :root{
      --blue-500: #0b5fd7;
      --blue-600: #0748b7;
      --muted: #4b6b8a;
      --danger: #b00020;
    }

    html,body{
      height:100%;
      margin:0;
      padding:0;
      font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
      /* keep background image untouched (no blue overlay) */
      background: url('pet.jpg') no-repeat center center fixed;
      background-size: cover;
      -webkit-font-smoothing:antialiased;
      -moz-osx-font-smoothing:grayscale;
      display:flex;
      align-items:center;
      justify-content:center;
    }

    form {
      /* apply blue theme only to the registration box */
      background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(233,243,255,0.9));
      padding: 34px;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(11,95,215,0.12);
      width: 380px;
      border: 1px solid rgba(11,95,215,0.12);
    }

    h2 {
      text-align: center;
      color: var(--blue-500);
      margin: 0 0 18px 0;
      font-weight:700;
      letter-spacing:0.2px;
    }

    .field { margin-bottom: 14px; }

    label {
      display: block;
      margin-bottom: 6px;
      color: var(--muted);
      font-weight: 600;
      font-size: 0.95rem;
    }

    input {
      width: 100%;
      padding: 11px;
      border: 1px solid rgba(11,95,215,0.12);
      border-radius: 8px;
      background: #fff;
      box-sizing: border-box;
      font-size: 0.95rem;
      color: #153244;
      transition: box-shadow .12s ease, border-color .12s ease;
    }

    input:focus {
      outline: none;
      border-color: var(--blue-500);
      box-shadow: 0 6px 18px rgba(11,95,215,0.08), 0 0 0 4px rgba(11,95,215,0.06);
    }

    input.invalid {
      border-color: var(--danger);
      background: #fff5f5;
    }

    .field-error {
      color: var(--danger);
      font-size: 0.88rem;
      margin-top: 6px;
      min-height: 18px;
    }

    button {
      background: linear-gradient(180deg, var(--blue-500), var(--blue-600));
      color: white;
      border: none;
      padding: 11px;
      border-radius: 10px;
      cursor: pointer;
      width: 100%;
      font-size: 1rem;
      font-weight: 600;
      box-shadow: 0 6px 18px rgba(11,95,215,0.12);
      transition: transform .08s ease, box-shadow .12s ease;
    }

    button:hover { transform: translateY(-1px); box-shadow: 0 10px 26px rgba(11,95,215,0.16); }
    button:active { transform: translateY(0); }

    .login-link {
      text-align: center;
      margin-top: 12px;
      font-size: 0.95rem;
      color: var(--muted);
    }
    .login-link a {
      color: var(--blue-500);
      text-decoration: none;
      font-weight: 700;
    }
    .login-link a:hover { text-decoration: underline; }

    .message { text-align:center; margin-bottom:12px; font-weight:700; }
    .message.success { color: #0b8a3a; }
    .message.error   { color: var(--danger); }
  </style>
</head>
<body>

  <form id="regForm" method="POST" action="" novalidate>
    <h2>Register</h2>

    <?php if (!empty($flash)): ?>
      <div class="message <?php echo $flash['success'] ? 'success' : 'error'; ?>">
        <?php echo htmlspecialchars($flash['message']); ?>
      </div>
    <?php endif; ?>

    <div class="field">
      <label for="full_name">Full Name</label>
      <input id="full_name" type="text" name="full_name" required
             value="<?php echo htmlspecialchars($old['full_name'] ?? '', ENT_QUOTES); ?>">
      <div class="field-error" id="err_full_name"></div>
    </div>

    <div class="field">
      <label for="email">Email</label>
      <input id="email" type="email" name="email" required
             value="<?php echo htmlspecialchars($old['email'] ?? '', ENT_QUOTES); ?>">
      <div class="field-error" id="err_email"></div>
    </div>

    <div class="field">
      <label for="phone_number">Phone Number</label>
      <input id="phone_number" type="text" name="phone_number" pattern="[89][0-9]{9}" title="Start with 8 or 9 and contain exactly 10 digits"
             value="<?php echo htmlspecialchars($old['phone_number'] ?? '', ENT_QUOTES); ?>">
      <div class="field-error" id="err_phone_number"></div>
    </div>

    <div class="field">
      <label for="username">Username</label>
      <input id="username" type="text" name="username" required
             value="<?php echo htmlspecialchars($old['username'] ?? '', ENT_QUOTES); ?>">
      <div class="field-error" id="err_username"></div>
    </div>

    <div class="field">
      <label for="password">Password</label>
      <input id="password" type="password" name="password" required>
      <div class="field-error" id="err_password"></div>
    </div>

    <button type="submit">Register</button>

    <div class="login-link">
      Already have an account? <a href="login.php">Login</a>
    </div>
  </form>

  <script>
    (function(){
      const reFullName = /^[\p{L} '\-]{2,100}$/u;
      const reEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      const rePhone = /^[89][0-9]{9}$/;
      const reUsername = /^[A-Za-z0-9_]{3,20}$/;
      const rePassword = /^(?=.*[A-Za-z])(?=.*\d).{8,}$/;

      const fields = {
        full_name: { el: document.getElementById('full_name'), err: document.getElementById('err_full_name'), re: reFullName, msg: "2–100 letters, spaces, apostrophes, or hyphens only." },
        email: { el: document.getElementById('email'), err: document.getElementById('err_email'), re: reEmail, msg: "Enter a valid email address." },
        phone_number: { el: document.getElementById('phone_number'), err: document.getElementById('err_phone_number'), re: rePhone, msg: "Start with 8 or 9 and have exactly 10 digits.", optional: true },
        username: { el: document.getElementById('username'), err: document.getElementById('err_username'), re: reUsername, msg: "3–20 chars: letters, numbers, or underscores." },
        password: { el: document.getElementById('password'), err: document.getElementById('err_password'), re: rePassword, msg: "At least 8 chars, one letter and one number." }
      };

      document.getElementById('regForm').addEventListener('submit', function(e){
        let valid = true;
        for (const key in fields) {
          const f = fields[key];
          const val = f.el.value.trim();
          const optional = f.optional && val === '';
          const ok = optional || f.re.test(val);
          setError(f, ok ? '' : f.msg);
          if (!ok && valid) valid = false;
        }
        if (!valid) e.preventDefault();
      });

      function setError(f, msg) {
        f.err.textContent = msg;
        if (msg) f.el.classList.add('invalid');
        else f.el.classList.remove('invalid');
      }
    })();
  </script>

</body>
</html>
