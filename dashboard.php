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

<?php require_once "includes/footer.php"; ?>
