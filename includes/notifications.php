<?php

if (!function_exists('add_notification')) {
    function add_notification($conn, $user_id, $type, $title, $message, $booking_id = null)
    {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, booking_id, type, title, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $user_id, $booking_id, $type, $title, $message);
        $stmt->execute();
    }
}

if (!function_exists('mark_notification_read')) {
    function mark_notification_read($conn, $notification_id, $user_id)
    {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notification_id, $user_id);
        $stmt->execute();
    }
}
?>