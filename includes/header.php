<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . " | StudyBuddy" : "StudyBuddy"; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="container nav-wrap">
        <a class="logo" href="index.php">StudyBuddy</a>
        <nav aria-label="Main navigation">
            <a href="index.php">Home</a>
            <a href="about.php">About Us</a>
            <a href="tutors.php">Find a Tutor</a>
            <a href="contact.php">Contact Us</a>
            <?php if (isset($_SESSION["user_id"])): ?>
                <a href="dashboard.php">My Dashboard</a>
                <a class="nav-button" href="logout.php">Log Out</a>
            <?php else: ?>
                <a href="login.php">Log In</a>
                <a class="nav-button" href="register.php">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main>
