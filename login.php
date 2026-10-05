<?php
session_start();
require_once "includes/db.php";

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

$page_title = "Log In";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare("SELECT user_id, name, role, password_hash FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user["password_hash"])) {
        session_regenerate_id(true);
        $_SESSION["user_id"] = $user["user_id"];
        $_SESSION["name"] = $user["name"];
        $_SESSION["role"] = $user["role"];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Email or password is incorrect.";
    }
}

require_once "includes/header.php";
?>
<section class="page-header">
    <div class="container">
        <h1>Log In</h1>
        <p>Access your StudyBuddy dashboard.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="form-box">
            <?php if ($error): ?><div class="message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button class="button" type="submit">Log In</button>
            </form>
            <p class="small">Do not have an account? <a href="register.php">Register here</a>.</p>
        </div>
    </div>
</section>
<?php require_once "includes/footer.php"; ?>
