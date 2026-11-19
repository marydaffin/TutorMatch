
<?php
session_start();
require_once 'db.php';

$db = $conn ?? $pdo ?? null;

if (!$db instanceof PDO) {
    die("Database connection error. Check db.php.");
}

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Identify the tutor using the ID from the URL
$tutor_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$tutor_id || $tutor_id <= 0) {
    http_response_code(400);
    exit("Invalid tutor ID.");
}

// Retrieve this specific tutor
$stmt = $db->prepare("
    SELECT
        u.user_id,
        u.first_name,
        u.last_name,
        u.role,
        t.major,
        t.bio,
        t.hourly_rate,
        t.experience,
        t.is_active
    FROM users u
    INNER JOIN tutor_profiles t
        ON u.user_id = t.tutor_id
    WHERE u.user_id = ?
      AND u.role IN ('Tutor', 'Both')
      AND t.is_active = 1
");

$stmt->execute([$tutor_id]);
$tutor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tutor) {
    http_response_code(404);
    exit("Tutor not found or profile is inactive.");
}

// Only Student and Both accounts can book
$isStudent = isset($_SESSION['user_id']) &&
    in_array(
        $_SESSION['role'] ?? '',
        ['Student', 'Both'],
        true
    );

if (empty($_SESSION['booking_csrf'])) {
    $_SESSION['booking_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

// Handle booking form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$isStudent) {
        $error = "Please log in with a student account to book.";
    }
    elseif (
        !hash_equals(
            $_SESSION['booking_csrf'],
            $_POST['csrf_token'] ?? ''
        )
    ) {
        $error = "Invalid form submission.";
    }
    else {
        $student_id = (int)$_SESSION['user_id'];

        $booking_date = $_POST['booking_date'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';

        $start = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i',
            $booking_date . ' ' . $start_time
        );

        $end = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i',
            $booking_date . ' ' . $end_time
        );

        if (!$start || !$end) {
            $error = "Please enter a valid date and time.";
        }
        elseif ($start <= new DateTimeImmutable()) {
            $error = "Please select a future appointment.";
        }
        elseif ($end <= $start) {
            $error = "End time must be after start time.";
        }
        elseif (
            $start->format('Y-m-d') !== $booking_date ||
            $start->format('H:i') !== $start_time ||
            $end->format('Y-m-d') !== $booking_date ||
            $end->format('H:i') !== $end_time
        ) {
            $error = "Please enter valid appointment times.";
        }
        elseif ($student_id === $tutor_id) {
            $error = "You cannot book yourself as a tutor.";
        }
        else {

            try {
                // Assumed bookings columns:
                // student_id, tutor_id, booking_date,
                // start_time, end_time, status

                // Check for overlapping active appointments
                $check = $db->prepare("
                    SELECT COUNT(*)
                    FROM bookings
                    WHERE tutor_id = ?
                      AND booking_date = ?
                      AND status IN ('Pending', 'Confirmed')
                      AND start_time < ?
                      AND end_time > ?
                ");

                $check->execute([
                    $tutor_id,
                    $booking_date,
                    $end_time,
                    $start_time
                ]);

                if ($check->fetchColumn() > 0) {
                    $error = "This tutor already has an appointment at that time.";
                }
                else {
                    $stmt = $db->prepare("
                        INSERT INTO bookings
                        (student_id, tutor_id, booking_date,
                         start_time, end_time, status)
                        VALUES (?, ?, ?, ?, ?, 'Pending')
                    ");

                    $stmt->execute([
                        $student_id,
                        $tutor_id,
                        $booking_date,
                        $start_time,
                        $end_time
                    ]);

                    $success = "Your appointment request has been submitted!";

                    $_SESSION['booking_csrf'] = bin2hex(
                        random_bytes(32)
                    );
                }

            } catch (PDOException $e) {
                error_log($e->getMessage());

                $error = "Unable to save your appointment. Check the bookings table structure.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        <?= e($tutor['first_name']) ?> - TutorMatch
    </title>

    <link rel="stylesheet" href="css/styles.css?v=9">
</head>

<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="profile-container">

    <a href="index.php#find-tutors" class="back-link">
        &larr; Back to Tutors
    </a>

    <section class="profile-card">

        <!-- Tutor Information -->
        <div class="profile-header">

            <div class="profile-avatar">
                <?= e(strtoupper(
                    substr($tutor['first_name'], 0, 1)
                )) ?>
            </div>

            <div>
                <h1>
                    <?= e(
                        $tutor['first_name'] . ' ' .
                        $tutor['last_name']
                    ) ?>
                </h1>

                <p class="profile-major">
                    <?= e($tutor['major'] ?? 'Major not provided') ?>
                </p>

                <p class="profile-rate">
                    $<?= number_format(
                        (float)$tutor['hourly_rate'],
                        2
                    ) ?> / hour
                </p>
            </div>

        </div>

        <hr>

        <h2>About Me</h2>

        <p>
            <?= nl2br(e(
                $tutor['bio'] ?: 'No biography provided.'
            )) ?>
        </p>

        <h2>Tutoring Experience</h2>

        <p>
            <?= nl2br(e(
                $tutor['experience'] ?: 'No experience listed.'
            )) ?>
        </p>

        <hr>

        <!-- Appointment Booking -->
        <h2>Book a Tutoring Session</h2>

        <?php if ($error): ?>
            <p class="error"><?= e($error) ?></p>
        <?php endif; ?>

        <?php if ($success): ?>
            <p class="success"><?= e($success) ?></p>
        <?php endif; ?>

        <?php if (!isset($_SESSION['user_id'])): ?>

            <p>
                Please log in to book an appointment
                with this tutor.
            </p>

            <a href="login.php" class="profile-button">
                Login to Book
            </a>

        <?php elseif (!$isStudent): ?>

            <p>
                Appointment booking is available to
                Student and Both accounts.
            </p>

        <?php else: ?>

            <form
                action="tutor_profile.php?id=<?= (int)$tutor_id ?>"
                method="POST"
                class="booking-form"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['booking_csrf']) ?>"
                >

                <label for="booking_date">
                    Appointment Date
                </label>

                <input
                    type="date"
                    id="booking_date"
                    name="booking_date"
                    min="<?= date('Y-m-d') ?>"
                    required
                >

                <label for="start_time">
                    Start Time
                </label>

                <input
                    type="time"
                    id="start_time"
                    name="start_time"
                    required
                >

                <label for="end_time">
                    End Time
                </label>

                <input
                    type="time"
                    id="end_time"
                    name="end_time"
                    required
                >

                <button type="submit">
                    Request Appointment
                </button>

            </form>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
