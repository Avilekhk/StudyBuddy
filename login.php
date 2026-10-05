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

    // This page is student-only. Enforce role = 'student' server-side.
    $role = 'student';
    $stmt = $conn->prepare("SELECT user_id, name, role, password_hash FROM users WHERE email = ? AND role = ?");
    $stmt->bind_param("ss", $email, $role);
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
        $error = "Email or password is incorrect for a student account.";
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
        <div class="form-box login-split">
            <?php if ($error): ?><div class="message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <div>
                    <strong>Student login</strong>
                </div>
                <div>
                    <a class="button" href="tutor_login.php">Are you a tutor?</a>
                </div>
            </div>

            <form method="post" class="form-box" id="login-form" style="padding:20px;">
                <input type="hidden" name="role" value="student">
                <div class="form-group">
                    <label for="login-email">Email</label>
                    <input type="email" id="login-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" name="password" required>
                </div>
                <div class="form-group">
                    <button class="button" id="login-submit" type="submit">Log In</button>
                </div>
            </form>
            <p class="small">Do not have an account? <a href="register.php">Register here</a>.</p>
        </div>
    </div>
</section>
<?php require_once "includes/footer.php";
?>