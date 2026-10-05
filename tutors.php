<?php
require_once "includes/db.php";
$page_title = "Find a Tutor";

$subject_id = isset($_GET["subject_id"]) ? (int) $_GET["subject_id"] : 0;
$subjects = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name");

if ($subject_id > 0) {
    $stmt = $conn->prepare("
        SELECT a.slot_id, a.day_time, u.user_id, u.name, s.subject_name
        FROM availability a
        INNER JOIN users u ON a.tutor_id = u.user_id
        INNER JOIN subjects s ON a.subject_id = s.subject_id
        WHERE u.role = 'tutor' AND a.is_booked = 0 AND a.subject_id = ?
        ORDER BY u.name, a.day_time
    ");
    $stmt->bind_param("i", $subject_id);
} else {
    $stmt = $conn->prepare("
        SELECT a.slot_id, a.day_time, u.user_id, u.name, s.subject_name
        FROM availability a
        INNER JOIN users u ON a.tutor_id = u.user_id
        INNER JOIN subjects s ON a.subject_id = s.subject_id
        WHERE u.role = 'tutor' AND a.is_booked = 0
        ORDER BY u.name, a.day_time
    ");
}
$stmt->execute();
$result = $stmt->get_result();

$tutors = [];
while ($row = $result->fetch_assoc()) {
    $tutors[$row["user_id"]]["name"] = $row["name"];
    $tutors[$row["user_id"]]["slots"][] = $row;
}

require_once "includes/header.php";
?>
<section class="page-header">
    <div class="container">
        <h1>Find a Tutor</h1>
        <p>Search by subject and view open time slots.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <form class="search-box" method="get">
            <div class="search-grid">
                <div>
                    <label for="subject_id">Subject</label>
                    <select id="subject_id" name="subject_id">
                        <option value="0">All subjects</option>
                        <?php while ($subject = $subjects->fetch_assoc()): ?>
                            <option value="<?php echo $subject["subject_id"]; ?>" <?php echo $subject_id === (int)$subject["subject_id"] ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($subject["subject_name"]); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div></div>
                <div><button class="button" type="submit">Search</button></div>
            </div>
        </form>

        <?php if (count($tutors) === 0): ?>
            <div class="empty">
                <p>No tutors have open time slots for this search yet.</p>
            </div>
        <?php else: ?>
            <div class="tutor-list">
                <?php foreach ($tutors as $tutor): ?>
                    <article class="tutor-card">
                        <h3><?php echo htmlspecialchars($tutor["name"]); ?></h3>
                        <p><strong>Available sessions:</strong></p>
                        <?php foreach ($tutor["slots"] as $slot): ?>
                            <div class="slot">
                                <div>
                                    <strong><?php echo htmlspecialchars($slot["subject_name"]); ?></strong><br>
                                    <span class="small"><?php echo date("d M Y, g:i A", strtotime($slot["day_time"])); ?></span>
                                </div>
                                <?php if (isset($_SESSION["user_id"]) && $_SESSION["role"] === "student"): ?>
                                    <a class="button" href="booking.php?slot_id=<?php echo $slot["slot_id"]; ?>">Book</a>
                                <?php elseif (!isset($_SESSION["user_id"])): ?>
                                    <a class="button" href="login.php">Log in to book</a>
                                <?php else: ?>
                                    <span class="small">Tutor account</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once "includes/footer.php"; ?>
