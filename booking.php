<?php
session_start();
require_once "includes/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "student") {
    header("Location: dashboard.php");
    exit;
}

$slot_id = isset($_GET["slot_id"]) ? (int)$_GET["slot_id"] : (int)($_POST["slot_id"] ?? 0);
$page_title = "Book a Session";
$error = "";
$message = "";

$stmt = $conn->prepare("
    SELECT a.slot_id, a.day_time, a.is_booked, u.name AS tutor_name, s.subject_name
    FROM availability a
    INNER JOIN users u ON a.tutor_id = u.user_id
    INNER JOIN subjects s ON a.subject_id = s.subject_id
    WHERE a.slot_id = ?
");
$stmt->bind_param("i", $slot_id);
$stmt->execute();
$slot = $stmt->get_result()->fetch_assoc();

if (!$slot) {
    $error = "The selected time slot could not be found.";
} elseif ($slot["is_booked"]) {
    $error = "This time slot has already been booked.";
} elseif ($_SERVER["REQUEST_METHOD"] === "POST") {
    $conn->begin_transaction();

    try {
        $lock = $conn->prepare("SELECT is_booked FROM availability WHERE slot_id = ? FOR UPDATE");
        $lock->bind_param("i", $slot_id);
        $lock->execute();
        $locked = $lock->get_result()->fetch_assoc();

        if (!$locked || $locked["is_booked"]) {
            throw new Exception("This time slot has already been booked.");
        }

        $book = $conn->prepare("INSERT INTO bookings (student_id, slot_id, status) VALUES (?, ?, 'pending')");
        $book->bind_param("ii", $_SESSION["user_id"], $slot_id);
        $book->execute();

        $update = $conn->prepare("UPDATE availability SET is_booked = 1 WHERE slot_id = ?");
        $update->bind_param("i", $slot_id);
        $update->execute();

        $conn->commit();
        $message = "Your booking request has been submitted.";
    } catch (Throwable $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

require_once "includes/header.php";
?>
<section class="page-header">
    <div class="container">
        <h1>Book a Session</h1>
        <p>Review the session details before confirming your booking.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="form-box">
            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
                <a class="button" href="dashboard.php">Go to Dashboard</a>
            <?php elseif ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                <a class="button secondary" href="tutors.php">Back to Tutors</a>
            <?php else: ?>
                <p><strong>Tutor:</strong> <?php echo htmlspecialchars($slot["tutor_name"]); ?></p>
                <p><strong>Subject:</strong> <?php echo htmlspecialchars($slot["subject_name"]); ?></p>
                <p><strong>Time:</strong> <?php echo date("d M Y, g:i A", strtotime($slot["day_time"])); ?></p>

                <form method="post" data-confirm="Confirm this booking request?">
                    <input type="hidden" name="slot_id" value="<?php echo $slot_id; ?>">
                    <button class="button" type="submit">Confirm Booking</button>
                    <a class="button secondary" href="tutors.php">Cancel</a>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
<script src="assets/js/script.js"></script>
<?php require_once "includes/footer.php"; ?>
