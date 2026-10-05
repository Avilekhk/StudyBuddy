<?php
session_start();
require_once "includes/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$page_title = "My Dashboard";
$user_id = (int)$_SESSION["user_id"];
$role = $_SESSION["role"];
$message = "";
$error = "";

if ($role === "tutor" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $subject_id = (int)($_POST["subject_id"] ?? 0);
    $day_time_raw = trim($_POST["day_time"] ?? "");

    if ($subject_id <= 0 || $day_time_raw === "") {
        $error = "Please select a subject and time.";
    } else {
        $day_time = str_replace("T", " ", $day_time_raw) . ":00";
        $stmt = $conn->prepare("INSERT INTO availability (tutor_id, subject_id, day_time, is_booked) VALUES (?, ?, ?, 0)");
        $stmt->bind_param("iis", $user_id, $subject_id, $day_time);
        if ($stmt->execute()) {
            $message = "Availability added.";
        } else {
            $error = "Could not add availability.";
        }
    }
}

require_once "includes/header.php";
?>
<section class="page-header">
    <div class="container">
        <h1>My Dashboard</h1>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION["name"]); ?>.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if ($message): ?><div class="message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <?php if ($role === "student"): ?>
            <h2>My Bookings</h2>
            <?php
            $stmt = $conn->prepare("
                SELECT b.booking_id, b.status, a.day_time, u.name AS tutor_name, s.subject_name
                FROM bookings b
                INNER JOIN availability a ON b.slot_id = a.slot_id
                INNER JOIN users u ON a.tutor_id = u.user_id
                INNER JOIN subjects s ON a.subject_id = s.subject_id
                WHERE b.student_id = ?
                ORDER BY a.day_time DESC
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $bookings = $stmt->get_result();
            ?>
            <?php if ($bookings->num_rows === 0): ?>
                <div class="empty">
                    <p>You do not have any bookings yet.</p>
                    <a class="button" href="tutors.php">Find a Tutor</a>
                </div>
            <?php else: ?>
                <table>
                    <thead><tr><th>Subject</th><th>Tutor</th><th>Time</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php while ($booking = $bookings->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking["subject_name"]); ?></td>
                            <td><?php echo htmlspecialchars($booking["tutor_name"]); ?></td>
                            <td><?php echo date("d M Y, g:i A", strtotime($booking["day_time"])); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($booking["status"])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        <?php else: ?>
            <div class="form-box">
                <h2>Add Availability</h2>
                <p>Publish an open time slot for students to book.</p>
                <form method="post">
                    <div class="form-group">
                        <label for="subject_id">Subject</label>
                        <select id="subject_id" name="subject_id" required>
                            <option value="">Select a subject</option>
                            <?php
                            $subjects = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name");
                            while ($subject = $subjects->fetch_assoc()):
                            ?>
                                <option value="<?php echo $subject["subject_id"]; ?>"><?php echo htmlspecialchars($subject["subject_name"]); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="day_time">Date and time</label>
                        <input type="datetime-local" id="day_time" name="day_time" required>
                    </div>
                    <button class="button" type="submit">Add Time Slot</button>
                </form>
            </div>

            <h2>My Availability</h2>
            <?php
            $stmt = $conn->prepare("
                SELECT a.slot_id, a.day_time, a.is_booked, s.subject_name
                FROM availability a
                INNER JOIN subjects s ON a.subject_id = s.subject_id
                WHERE a.tutor_id = ?
                ORDER BY a.day_time DESC
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $availability = $stmt->get_result();
            ?>
            <?php if ($availability->num_rows === 0): ?>
                <div class="empty"><p>You have not added any availability yet.</p></div>
            <?php else: ?>
                <table>
                    <thead><tr><th>Subject</th><th>Time</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php while ($slot = $availability->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($slot["subject_name"]); ?></td>
                            <td><?php echo date("d M Y, g:i A", strtotime($slot["day_time"])); ?></td>
                            <td><?php echo $slot["is_booked"] ? "Booked" : "Open"; ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2>Student Booking Requests</h2>
            <?php
            $stmt = $conn->prepare("
                SELECT b.booking_id, b.status, u.name AS student_name, s.subject_name, a.day_time
                FROM bookings b
                INNER JOIN availability a ON b.slot_id = a.slot_id
                INNER JOIN users u ON b.student_id = u.user_id
                INNER JOIN subjects s ON a.subject_id = s.subject_id
                WHERE a.tutor_id = ?
                ORDER BY a.day_time DESC
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $requests = $stmt->get_result();
            ?>
            <?php if ($requests->num_rows === 0): ?>
                <div class="empty"><p>You do not have any booking requests yet.</p></div>
            <?php else: ?>
                <table>
                    <thead><tr><th>Student</th><th>Subject</th><th>Time</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php while ($request = $requests->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request["student_name"]); ?></td>
                            <td><?php echo htmlspecialchars($request["subject_name"]); ?></td>
                            <td><?php echo date("d M Y, g:i A", strtotime($request["day_time"])); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($request["status"])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php require_once "includes/footer.php"; ?>
