<?php
session_start();
require_once "includes/db.php";
require_once "includes/notifications.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$page_title = "My Dashboard";
$user_id = (int) $_SESSION["user_id"];
$role = $_SESSION["role"];
$flash_message = $_SESSION["dashboard_flash"] ?? "";
$flash_error = $_SESSION["dashboard_error"] ?? "";
unset($_SESSION["dashboard_flash"], $_SESSION["dashboard_error"]);

$allowed_tabs = $role === "tutor"
    ? ["requests", "availability", "notifications"]
    : ["bookings", "notifications"];
$default_tab = $allowed_tabs[0];
$active_tab = $_GET["tab"] ?? $default_tab;
if (!in_array($active_tab, $allowed_tabs, true)) {
    $active_tab = $default_tab;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $return_tab = $_POST["return_tab"] ?? $active_tab;

    try {
        // Student creating a tutoring request
        if ($role === "student" && isset($_POST["request_subject_id"]) && isset($_POST["request_day_time"])) {
            $subject_id = (int) ($_POST["request_subject_id"] ?? 0);
            $day_time_raw = trim($_POST["request_day_time"] ?? "");
            $notes = trim($_POST["request_notes"] ?? "");
            if ($subject_id <= 0 || $day_time_raw === "") {
                throw new Exception("Please select a subject and enter a date/time for your request.");
            }
            $day_time = str_replace("T", " ", $day_time_raw) . ":00";
            $stmt = $conn->prepare("INSERT INTO student_requests (student_id, subject_id, day_time, notes) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $user_id, $subject_id, $day_time, $notes);
            if ($stmt->execute()) {
                $_SESSION["dashboard_flash"] = "Your tutoring request has been posted.";
            } else {
                throw new Exception("Could not create request.");
            }
            header("Location: dashboard.php?tab=bookings");
            exit;
        }

        // Tutor adding availability (form in availability panel submits subject_id and day_time)
        if ($role === "tutor" && isset($_POST["subject_id"]) && isset($_POST["day_time"])) {
            $subject_id = (int) ($_POST["subject_id"] ?? 0);
            $day_time_raw = trim($_POST["day_time"] ?? "");
            if ($subject_id <= 0 || $day_time_raw === "") {
                throw new Exception("Please select a subject and enter a date/time.");
            }
            $day_time = str_replace("T", " ", $day_time_raw) . ":00";
            $stmt = $conn->prepare("INSERT INTO availability (tutor_id, subject_id, day_time, is_booked) VALUES (?, ?, ?, 0)");
            $stmt->bind_param("iis", $user_id, $subject_id, $day_time);
            if ($stmt->execute()) {
                $_SESSION["dashboard_flash"] = "Availability added.";
            } else {
                throw new Exception("Could not add availability.");
            }
            header("Location: dashboard.php?tab=availability");
            exit;
        }

        if ($action === "mark_notification_read") {
            $notification_id = (int) ($_POST["notification_id"] ?? 0);
            if ($notification_id > 0) {
                mark_notification_read($conn, $notification_id, $user_id);
                $_SESSION["dashboard_flash"] = "Notification updated.";
            }
        } elseif ($role === "tutor" && in_array($action, ["accept_booking", "decline_booking"], true)) {
            $booking_id = (int) ($_POST["booking_id"] ?? 0);
            $conn->begin_transaction();

            $stmt = $conn->prepare("\n                SELECT b.booking_id, b.status, b.student_id, b.slot_id, a.tutor_id, a.day_time, s.subject_name, u.name AS student_name\n                FROM bookings b\n                INNER JOIN availability a ON b.slot_id = a.slot_id\n                INNER JOIN subjects s ON a.subject_id = s.subject_id\n                INNER JOIN users u ON b.student_id = u.user_id\n                WHERE b.booking_id = ? AND a.tutor_id = ?\n                FOR UPDATE\n            ");
            $stmt->bind_param("ii", $booking_id, $user_id);
            $stmt->execute();
            $booking = $stmt->get_result()->fetch_assoc();

            if (!$booking) {
                throw new Exception("Booking request could not be found.");
            }

            if ($booking["status"] !== "pending") {
                throw new Exception("This request has already been processed.");
            }

            if ($action === "accept_booking") {
                $update = $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE booking_id = ?");
                $update->bind_param("i", $booking_id);
                $update->execute();

                add_notification(
                    $conn,
                    (int) $booking["student_id"],
                    "booking_accepted",
                    "Booking accepted",
                    "Your session for " . $booking["subject_name"] . " on " . date("d M Y, g:i A", strtotime($booking["day_time"])) . " has been accepted.",
                    $booking_id
                );

                $_SESSION["dashboard_flash"] = "Booking request accepted.";
            } else {
                $update = $conn->prepare("UPDATE bookings SET status = 'declined' WHERE booking_id = ?");
                $update->bind_param("i", $booking_id);
                $update->execute();

                $slot_update = $conn->prepare("UPDATE availability SET is_booked = 0 WHERE slot_id = ?");
                $slot_update->bind_param("i", $booking["slot_id"]);
                $slot_update->execute();

                add_notification(
                    $conn,
                    (int) $booking["student_id"],
                    "booking_declined",
                    "Booking declined",
                    "Your session for " . $booking["subject_name"] . " on " . date("d M Y, g:i A", strtotime($booking["day_time"])) . " was declined.",
                    $booking_id
                );

                $_SESSION["dashboard_flash"] = "Booking request declined.";
            }

            $conn->commit();
            header("Location: dashboard.php?tab=" . urlencode($return_tab));
            exit;
        }
        // Tutor joining a student request
        elseif ($role === "tutor" && $action === "join_request") {
            $request_id = (int) ($_POST["request_id"] ?? 0);
            $conn->begin_transaction();

            $rq = $conn->prepare("SELECT request_id, student_id, subject_id, day_time, status FROM student_requests WHERE request_id = ? FOR UPDATE");
            $rq->bind_param("i", $request_id);
            $rq->execute();
            $request = $rq->get_result()->fetch_assoc();

            if (!$request || $request["status"] !== 'open') {
                throw new Exception("Request not available.");
            }

            // Create availability for this tutor and immediately mark as booked
            $insert_av = $conn->prepare("INSERT INTO availability (tutor_id, subject_id, day_time, is_booked) VALUES (?, ?, ?, 1)");
            $insert_av->bind_param("iis", $user_id, $request["subject_id"], $request["day_time"]);
            $insert_av->execute();
            $slot_id = $conn->insert_id;

            // Create confirmed booking for the student
            $book = $conn->prepare("INSERT INTO bookings (student_id, slot_id, status) VALUES (?, ?, 'confirmed')");
            $book->bind_param("ii", $request["student_id"], $slot_id);
            $book->execute();
            $booking_id = $conn->insert_id;

            // Update request as matched
            $upd = $conn->prepare("UPDATE student_requests SET status = 'matched', matched_slot_id = ?, matched_tutor_id = ? WHERE request_id = ?");
            $upd->bind_param("iii", $slot_id, $user_id, $request_id);
            $upd->execute();

            // Notifications
            add_notification($conn, (int)$request["student_id"], "request_matched", "Tutor joined your request", "A tutor has joined your request for " . date("d M Y, g:i A", strtotime($request["day_time"])) . ".", $booking_id);
            add_notification($conn, $user_id, "joined_request", "You joined a request", "You joined a student request for " . date("d M Y, g:i A", strtotime($request["day_time"])) . ".", $booking_id);

            $conn->commit();
            $_SESSION["dashboard_flash"] = "You have joined the request and confirmed the booking.";
            header("Location: dashboard.php?tab=requests");
            exit;
        }
    } catch (Throwable $e) {
        if ($conn->errno) {
            $conn->rollback();
        }
        $_SESSION["dashboard_error"] = $e->getMessage();
        header("Location: dashboard.php?tab=" . urlencode($return_tab));
        exit;
    }
}

require_once "includes/header.php";

$notifications_stmt = $conn->prepare("\n    SELECT notification_id, type, title, message, is_read, created_at\n    FROM notifications\n    WHERE user_id = ?\n    ORDER BY created_at DESC\n");
$notifications_stmt->bind_param("i", $user_id);
$notifications_stmt->execute();
$notifications = $notifications_stmt->get_result();

$unread_stmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_count = (int) ($unread_stmt->get_result()->fetch_assoc()["unread_count"] ?? 0);
?>
<section class="page-header">
    <div class="container">
        <h1>My Dashboard</h1>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION["name"]); ?>.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if ($flash_message): ?><div class="message"><?php echo htmlspecialchars($flash_message); ?></div><?php endif; ?>
        <?php if ($flash_error): ?><div class="message error"><?php echo htmlspecialchars($flash_error); ?></div><?php endif; ?>

        <div class="dashboard-tabs" role="tablist" aria-label="Dashboard sections">
            <?php if ($role === "student"): ?>
                <button type="button" class="dashboard-tab <?php echo $active_tab === "bookings" ? "active" : ""; ?>" data-tab-target="bookings">My Bookings</button>
                <button type="button" class="dashboard-tab <?php echo $active_tab === "notifications" ? "active" : ""; ?>" data-tab-target="notifications">Notifications<?php if ($unread_count > 0): ?> <span class="tab-badge"><?php echo $unread_count; ?></span><?php endif; ?></button>
            <?php else: ?>
                <button type="button" class="dashboard-tab <?php echo $active_tab === "requests" ? "active" : ""; ?>" data-tab-target="requests">Requests<?php if ($unread_count > 0): ?> <span class="tab-badge"><?php echo $unread_count; ?></span><?php endif; ?></button>
                <button type="button" class="dashboard-tab <?php echo $active_tab === "availability" ? "active" : ""; ?>" data-tab-target="availability">Availability</button>
                <button type="button" class="dashboard-tab <?php echo $active_tab === "notifications" ? "active" : ""; ?>" data-tab-target="notifications">Notifications<?php if ($unread_count > 0): ?> <span class="tab-badge"><?php echo $unread_count; ?></span><?php endif; ?></button>
            <?php endif; ?>
        </div>

        <div class="dashboard-panels">
            <?php if ($role === "student"): ?>
                <section class="dashboard-panel <?php echo $active_tab === "bookings" ? "active" : ""; ?>" data-tab-panel="bookings">
                    <h2>My Bookings</h2>
                    <?php
                    $stmt = $conn->prepare("\n                        SELECT b.booking_id, b.status, a.day_time, u.name AS tutor_name, s.subject_name\n                        FROM bookings b\n                        INNER JOIN availability a ON b.slot_id = a.slot_id\n                        INNER JOIN users u ON a.tutor_id = u.user_id\n                        INNER JOIN subjects s ON a.subject_id = s.subject_id\n                        WHERE b.student_id = ?\n                        ORDER BY a.day_time DESC\n                    ");
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

                    <hr>
                    <div class="form-box">
                        <h3>Create a tutoring request</h3>
                        <p>Request a tutor for a subject and preferred time. Tutors can join your request.</p>
                        <form method="post">
                            <div class="form-group">
                                <label for="request_subject_id">Subject</label>
                                <select id="request_subject_id" name="request_subject_id" required>
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
                                <label for="request_day_time">Date and time</label>
                                <input type="datetime-local" id="request_day_time" name="request_day_time" required>
                            </div>
                            <div class="form-group">
                                <label for="request_notes">Notes (optional)</label>
                                <input type="text" id="request_notes" name="request_notes">
                            </div>
                            <button class="button" type="submit">Post Request</button>
                        </form>
                    </div>

                    <h3>Your Requests</h3>
                    <?php
                    $rq = $conn->prepare("SELECT r.request_id, r.status, r.day_time, s.subject_name, r.matched_slot_id, r.matched_tutor_id FROM student_requests r INNER JOIN subjects s ON r.subject_id = s.subject_id WHERE r.student_id = ? ORDER BY r.created_at DESC");
                    $rq->bind_param("i", $user_id);
                    $rq->execute();
                    $my_requests = $rq->get_result();
                    ?>
                    <?php if ($my_requests->num_rows === 0): ?>
                        <div class="empty"><p>You have not created any requests yet.</p></div>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Subject</th><th>Time</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php while ($r = $my_requests->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r["subject_name"]); ?></td>
                                    <td><?php echo date("d M Y, g:i A", strtotime($r["day_time"])); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($r["status"])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>
            <?php else: ?>
                <section class="dashboard-panel <?php echo $active_tab === "requests" ? "active" : ""; ?>" data-tab-panel="requests">
                    <h2>Student Booking Requests</h2>
                    <?php
                    $stmt = $conn->prepare("\n                        SELECT b.booking_id, b.status, u.name AS student_name, s.subject_name, a.day_time\n                        FROM bookings b\n                        INNER JOIN availability a ON b.slot_id = a.slot_id\n                        INNER JOIN users u ON b.student_id = u.user_id\n                        INNER JOIN subjects s ON a.subject_id = s.subject_id\n                        WHERE a.tutor_id = ?\n                        ORDER BY CASE b.status WHEN 'pending' THEN 0 WHEN 'confirmed' THEN 1 ELSE 2 END, a.day_time DESC\n                    ");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $requests = $stmt->get_result();
                    ?>
                    <?php if ($requests->num_rows === 0): ?>
                        <div class="empty"><p>You do not have any booking requests yet.</p></div>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Student</th><th>Subject</th><th>Time</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php while ($request = $requests->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request["student_name"]); ?></td>
                                    <td><?php echo htmlspecialchars($request["subject_name"]); ?></td>
                                    <td><?php echo date("d M Y, g:i A", strtotime($request["day_time"])); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($request["status"])); ?></td>
                                    <td>
                                        <?php if ($request["status"] === "pending"): ?>
                                            <form method="post" class="inline-actions">
                                                <input type="hidden" name="return_tab" value="requests">
                                                <input type="hidden" name="booking_id" value="<?php echo $request["booking_id"]; ?>">
                                                <button class="button" type="submit" name="action" value="accept_booking">Accept</button>
                                                <button class="button secondary" type="submit" name="action" value="decline_booking">Decline</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="small">Processed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <hr>
                    <h3>Open Student Requests</h3>
                    <?php
                    $sr = $conn->prepare("SELECT r.request_id, r.student_id, r.day_time, r.notes, s.subject_name, u.name AS student_name FROM student_requests r INNER JOIN subjects s ON r.subject_id = s.subject_id INNER JOIN users u ON r.student_id = u.user_id WHERE r.status = 'open' ORDER BY r.created_at DESC");
                    $sr->execute();
                    $student_requests = $sr->get_result();
                    ?>
                    <?php if ($student_requests->num_rows === 0): ?>
                        <div class="empty"><p>There are no open student requests right now.</p></div>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Student</th><th>Subject</th><th>Time</th><th>Notes</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php while ($srq = $student_requests->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($srq["student_name"]); ?></td>
                                    <td><?php echo htmlspecialchars($srq["subject_name"]); ?></td>
                                    <td><?php echo date("d M Y, g:i A", strtotime($srq["day_time"])); ?></td>
                                    <td><?php echo htmlspecialchars($srq["notes"]); ?></td>
                                    <td>
                                        <form method="post" class="inline-actions">
                                            <input type="hidden" name="request_id" value="<?php echo $srq["request_id"]; ?>">
                                            <input type="hidden" name="action" value="join_request">
                                            <button class="button" type="submit">Join</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($role === "tutor"): ?>
                <section class="dashboard-panel <?php echo $active_tab === "availability" ? "active" : ""; ?>" data-tab-panel="availability">
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
                    $stmt = $conn->prepare("\n                        SELECT a.slot_id, a.day_time, a.is_booked, s.subject_name\n                        FROM availability a\n                        INNER JOIN subjects s ON a.subject_id = s.subject_id\n                        WHERE a.tutor_id = ?\n                        ORDER BY a.day_time DESC\n                    ");
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
                </section>
            <?php endif; ?>

            <section class="dashboard-panel <?php echo $active_tab === "notifications" ? "active" : ""; ?>" data-tab-panel="notifications">
                <div class="panel-header-row">
                    <h2>Notifications</h2>
                    <?php if ($unread_count > 0): ?>
                        <span class="small"><?php echo $unread_count; ?> unread</span>
                    <?php endif; ?>
                </div>

                <?php if ($notifications->num_rows === 0): ?>
                    <div class="empty"><p>No notifications yet.</p></div>
                <?php else: ?>
                    <div class="notification-list">
                        <?php while ($notification = $notifications->fetch_assoc()): ?>
                            <article class="notification-card <?php echo $notification["is_read"] ? "" : "unread"; ?>">
                                <div class="notification-head">
                                    <div>
                                        <h3><?php echo htmlspecialchars($notification["title"]); ?></h3>
                                        <p class="small"><?php echo date("d M Y, g:i A", strtotime($notification["created_at"])); ?></p>
                                    </div>
                                    <?php if (!$notification["is_read"]): ?>
                                        <span class="tab-badge">New</span>
                                    <?php endif; ?>
                                </div>
                                <p><?php echo htmlspecialchars($notification["message"]); ?></p>
                                <?php if (!$notification["is_read"]): ?>
                                    <form method="post" class="inline-actions">
                                        <input type="hidden" name="action" value="mark_notification_read">
                                        <input type="hidden" name="return_tab" value="notifications">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification["notification_id"]; ?>">
                                        <button class="button secondary" type="submit">Mark as read</button>
                                    </form>
                                <?php endif; ?>
                            </article>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>
<?php require_once "includes/footer.php"; ?>
