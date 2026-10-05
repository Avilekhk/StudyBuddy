<?php
session_start();
require_once "includes/db.php";

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

$page_title = "Register";
$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $role = $_POST["role"] ?? "";
    $password = $_POST["password"] ?? "";

    if ($name === "" || $email === "" || $password === "" || !in_array($role, ["student", "tutor"], true)) {
        $error = "Please complete all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $existing = $check->get_result();

        if ($existing->num_rows > 0) {
            $error = "An account with that email already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, role, password_hash) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $role, $hash);

            if ($stmt->execute()) {
                $message = "Registration successful. You can now log in.";
            } else {
                $error = "Registration could not be completed.";
            }
        }
    }
}

require_once "includes/header.php";
?>
<section class="page-header">
    <div class="container">
        <h1>Register</h1>
        <p>Create a StudyBuddy account as a student or tutor.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="form-box">
            <?php if ($message): ?><div class="message"><?php echo htmlspecialchars($message); ?> <a href="login.php">Log in</a>.</div><?php endif; ?>
            <?php if ($error): ?><div class="message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="role">Register as</label>
                    <select id="role" name="role" required>
                        <option value="">Select a role</option>
                        <option value="student">Student</option>
                        <option value="tutor">Tutor</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" minlength="6" required>
                </div>

                <button class="button" type="submit">Create Account</button>
            </form>
        </div>
    </div>
</section>
<?php require_once "includes/footer.php"; ?>
