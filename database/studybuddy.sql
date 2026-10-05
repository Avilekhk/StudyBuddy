CREATE DATABASE IF NOT EXISTS studybuddy
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE studybuddy;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    role ENUM('student', 'tutor') NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE subjects (
    subject_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL
);

CREATE TABLE availability (
    slot_id INT AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL,
    subject_id INT NOT NULL,
    day_time DATETIME NOT NULL,
    is_booked TINYINT(1) DEFAULT 0,
    FOREIGN KEY (tutor_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE RESTRICT
);

CREATE TABLE bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    slot_id INT NOT NULL,
    status ENUM('pending', 'confirmed', 'declined', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY one_booking_per_slot (slot_id),
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES availability(slot_id) ON DELETE CASCADE
);

INSERT INTO subjects (subject_name, description) VALUES
('Web Development', 'HTML, CSS, JavaScript and basic web development.'),
('PHP Programming', 'PHP programming and server-side web development.'),
('Database', 'MySQL and basic relational database concepts.'),
('Networking', 'Basic computer networking and network concepts.'),
('Programming', 'General programming support for university subjects.');

-- Demo tutor account:
-- Email: tutor@studybuddy.test
-- Password: password
-- The account can be created through register.php.
