<?php
session_start();
require_once "includes/db.php";

if (isset($_SESSION["user_id"]) && $_SESSION["role"] === "tutor") {
    header("Location: dashboard.php");
    exit;
}

$page_title = "Tutor Log In";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $role = 'tutor';
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
        $error = "Email or password is incorrect for tutor account.";
    }
}

require_once "includes/header.php";
?>
<section class="page-header">
    <div class="container">
        <h1>Tutor Log In</h1>
        <p>Access your tutor dashboard.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="form-box">
            <?php if ($error): ?><div class="message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="login-email">Email</label>
                    <input type="email" id="login-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" name="password" required>
                </div>
                <div class="form-group">
                    <button class="button" type="submit">Log In as Tutor</button>
                </div>
            </form>
            <p class="small">Not a tutor? <a href="login.php">Student login</a>.</p>
        </div>
    </div>
</section>
<?php require_once "includes/footer.php"; ?>
