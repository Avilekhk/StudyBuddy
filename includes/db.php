<?php
// StudyBuddy database connection
$host = getenv("DB_HOST") ?: "localhost";
$user = getenv("DB_USER") ?: "root";
$password = getenv("DB_PASSWORD") ?: "";
$database = getenv("DB_NAME") ?: "studybuddy";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Create notifications table if it does not exist. Keep this simple to avoid
// foreign-key dependency issues during initial setup or partial imports.
$sql = "CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    booking_id INT DEFAULT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(150) NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === false) {
    // Do not throw: table creation failures here are non-fatal during deployment.
    error_log('Could not ensure notifications table exists: ' . $conn->error);
}

// Create student_requests table if it does not exist (safe bootstrap)
$req_sql = "CREATE TABLE IF NOT EXISTS student_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    day_time DATETIME NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    status ENUM('open','matched','cancelled') DEFAULT 'open',
    matched_slot_id INT DEFAULT NULL,
    matched_tutor_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($req_sql) === false) {
    error_log('Could not ensure student_requests table exists: ' . $conn->error);
}
?>
