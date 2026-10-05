STUDYBUDDY - PHP / MySQL WEBSITE
================================

Technology:
- HTML5
- CSS3
- Small amount of JavaScript
- PHP
- MySQL
- XAMPP

PAGES
-----
1. index.php       Home
2. about.php       About Us
3. register.php    Register
4. login.php       Log In
5. tutors.php      Find a Tutor
6. booking.php     Book a Session
7. dashboard.php   My Dashboard
8. contact.php     Contact Us

The proposal originally lists seven main pages and does not list a separate
login page. Login is included here because the booking and dashboard features
need user authentication. The total remains within the assignment's 5-8 page
scope.

DATABASE
--------
The SQL file is in:
database/studybuddy.sql

XAMPP SETUP
-----------
1. Install/start XAMPP.
2. Start Apache and MySQL.
3. Copy the StudyBuddy folder into:
   C:\xampp\htdocs\
4. Open phpMyAdmin:
   http://localhost/phpmyadmin
5. Import database/studybuddy.sql
6. Open:
   http://localhost/StudyBuddy/

DATABASE LOGIN
--------------
The current db.php assumes:
Host: localhost
User: root
Password: empty
Database: studybuddy

If your XAMPP MySQL password is different, update includes/db.php.

NOTES
-----
- Passwords are stored with PHP password_hash().
- SQL queries that accept user input use prepared statements.
- Booking uses a transaction and row locking to reduce the risk of two
  students booking the same slot.
- The four core database tables follow the proposal. subject_id was added
  to availability because a tutor's available slot needs to identify which
  subject is being offered.
- Contact enquiries are acknowledged on screen because the proposal's
  four-table design does not include a contact_messages table.
