<?php
$page_title = "Home";
require_once "includes/header.php";
?>
<section class="hero">
    <div class="container">
        <h1>Find the right peer tutor for your study needs.</h1>
        <p>
            StudyBuddy is a simple peer tutoring booking platform for university students.
            Find tutors, check their available times and book a session in one place.
        </p>
        <div class="hero-actions">
            <a class="button" href="tutors.php">Find a Tutor</a>
            <a class="button secondary" href="register.php">Register</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2>Why StudyBuddy?</h2>
        <div class="cards">
            <article class="card">
                <h3>Find a Tutor</h3>
                <p>Search for peer tutors by subject and see their available time slots.</p>
            </article>
            <article class="card">
                <h3>Book a Session</h3>
                <p>Select an open time slot and send a booking request in a few clicks.</p>
            </article>
            <article class="card">
                <h3>Keep Track</h3>
                <p>Use your dashboard to view upcoming sessions and booking history.</p>
            </article>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2>How it works</h2>
        <div class="steps">
            <div class="step"><span class="step-number">1</span><strong> Register</strong><p>Create a student or tutor account.</p></div>
            <div class="step"><span class="step-number">2</span><strong> Search</strong><p>Find a tutor and view open time slots.</p></div>
            <div class="step"><span class="step-number">3</span><strong> Book</strong><p>Choose a suitable session time.</p></div>
            <div class="step"><span class="step-number">4</span><strong> Learn</strong><p>Attend the session and get support.</p></div>
        </div>
    </div>
</section>
<?php require_once "includes/footer.php"; ?>
