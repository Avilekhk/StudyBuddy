<?php
$page_title = "Contact Us";
$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $subject = trim($_POST["subject"] ?? "");
    $details = trim($_POST["details"] ?? "");

    if ($name === "" || $email === "" || $subject === "" || $details === "") {
        $error = "Please complete all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // For this class project, the enquiry is acknowledged on screen.
        // A separate contact_messages table was not included in the four-table proposal.
        $message = "Thank you, " . $name . ". Your enquiry has been received.";
    }
}

require_once "includes/header.php";
?>
<section class="page-header">
    <div class="container">
        <h1>Contact Us</h1>
        <p>Have a question about StudyBuddy? Send us an enquiry.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="form-box">
            <?php if ($message): ?><div class="message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" required>
                </div>
                <div class="form-group">
                    <label for="details">Message</label>
                    <textarea id="details" name="details" required></textarea>
                </div>
                <button class="button" type="submit">Send Enquiry</button>
            </form>
        </div>
    </div>
</section>
<?php require_once "includes/footer.php"; ?>
