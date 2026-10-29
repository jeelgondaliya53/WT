<?php
// Share the PHP session across the WT practical folders.
session_set_cookie_params([
    'path' => '/xampp/myproject/WT/',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

/* =========================
   DATABASE CONNECTION
   ========================= */
$conn = mysqli_connect("localhost", "root", "");
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS secure_login");
mysqli_select_db($conn, "secure_login");

/* =========================
   CREATE TABLE
   ========================= */
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL
)";
if (!mysqli_query($conn, $sql)) {
    die("Table creation failed: " . mysqli_error($conn));
}

/* Fix old tables where the password column was too short (hash got cut off) */
mysqli_query($conn, "ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL");

/* =========================
   CREATE / FIX TEST USERS
   ========================= */
function ensureUser($conn, $username, $plainPassword, $role)
{
    // Check whether the user exists and the stored hash is valid
    $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($row && password_verify($plainPassword, $row['password'])) {
        return; // already correct, nothing to do
    }

    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO users (username, password, role)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE password = VALUES(password), role = VALUES(role)"
    );
    mysqli_stmt_bind_param($stmt, "sss", $username, $hash, $role);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

ensureUser($conn, "admin", "admin123", "admin");
ensureUser($conn, "student", "student123", "student");

/* =========================
   LOGOUT
   ========================= */
if (isset($_POST['logout'])) {
    $_SESSION = array();
    session_destroy();
    header("Location: login.php");
    exit();
}

/* =========================
   SESSION TIMEOUT (5 minutes)
   ========================= */
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 300) {
        $_SESSION = array();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit();
    }
    $_SESSION['last_activity'] = time();
}

/* =========================
   LOGIN
   ========================= */
$error = "";

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, username, password, role FROM users WHERE username = ?"
    );
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);

        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();

            header("Location: login.php");
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Secure Login System</title>
<style>
body { font-family: Arial; background: #f2f2f2; text-align: center; padding-top: 60px; }
.box { width: 400px; margin: auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px gray; }
input { padding: 10px; margin: 8px; width: 80%; }
button { padding: 10px 25px; background: black; color: white; border: none; cursor: pointer; }
.error { color: red; }
</style>
</head>
<body>
<div class="box">

<?php if (isset($_SESSION['user_id'])): ?>

    <h2>Login Successful</h2>
    <h3>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h3>

    <?php if ($_SESSION['role'] == "admin"): ?>
        <h2>Admin Dashboard</h2>
        <p>You are logged in as <b>Admin</b>.</p>
    <?php else: ?>
        <h2>Student Dashboard</h2>
        <p>You are logged in as <b>Student</b>.</p>
    <?php endif; ?>

    <form method="post">
        <button type="submit" name="logout">Logout</button>
    </form>
    <p>Session timeout: 5 minutes</p>

<?php else: ?>

    <h2>Secure Login System</h2>

    <form method="post">
        <input type="text" name="username" placeholder="Enter username" required><br>
        <input type="password" name="password" placeholder="Enter password" required><br>
        <button type="submit" name="login">Login</button>
    </form>

    <?php
    if ($error != "") {
        echo "<p class='error'>" . htmlspecialchars($error) . "</p>";
    }
    if (isset($_GET['timeout'])) {
        echo "<p class='error'>Session expired. Please login again.</p>";
    }
    ?>

    <hr>
    <h4>Test Login Details</h4>
    <p><b>Admin</b><br>Username: admin<br>Password: admin123</p>
    <p><b>Student</b><br>Username: student<br>Password: student123</p>

<?php endif; ?>

</div>
</body>
</html>