
<?php
session_start();
require_once 'db.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$db = $conn ?? $pdo ?? null;

if (!$db instanceof PDO) {
    die("Database connection error. Check db.php.");
}

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$user_id = (int)$_SESSION['user_id'];

try {
    // Load current account from database
    $stmt = $db->prepare(
        "SELECT user_id, first_name, last_name, email, role
         FROM users
         WHERE user_id = ?"
    );

    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION = [];
        session_destroy();
        header("Location: login.php");
        exit;
    }

    $role = $user['role'];
    $tutorProfile = null;

    // Load tutor information where applicable
    if ($role === 'Tutor' || $role === 'Both') {
        $stmt = $db->prepare(
            "SELECT bio, major, hourly_rate,
                    experience, is_active
             FROM tutor_profiles
             WHERE tutor_id = ?"
        );

        $stmt->execute([$user_id]);

        $tutorProfile = $stmt->fetch(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit("Unable to load your dashboard.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TutorMatch</title>
    <link rel="stylesheet" href="css/styles.css?v=5">
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="dashboard-container">

    <h1>Welcome, <?= e($user['first_name']) ?>!</h1>

    <p>Manage your TutorMatch account here.</p>

    <section class="dashboard-card">
        <h2>My Account</h2>

        <p>
            <strong>Name:</strong>
            <?= e($user['first_name'] . ' ' . $user['last_name']) ?>
        </p>

        <p>
            <strong>Email:</strong>
            <?= e($user['email']) ?>
        </p>

        <p>
            <strong>Account Type:</strong>
            <?= e($role) ?>
        </p>
    </section>

    <?php if ($role === 'Student' || $role === 'Both'): ?>

        <section class="dashboard-card">
            <h2>Student Dashboard</h2>

            <p>
                Find tutors who can help you with your classes.
            </p>

            <a href="tutors.php" class="dashboard-button">
                Find Tutors
            </a>
        </section>

    <?php endif; ?>

    <?php if ($role === 'Tutor' || $role === 'Both'): ?>

        <section class="dashboard-card">
            <h2>My Tutor Profile</h2>

            <?php if ($tutorProfile): ?>

                <p>
                    <strong>Major:</strong>
                    <?= e($tutorProfile['major'] ?? 'Not provided') ?>
                </p>

                <p>
                    <strong>Hourly Rate:</strong>
                    $<?= number_format(
                        (float)$tutorProfile['hourly_rate'],
                        2
                    ) ?>
                </p>

                <p>
                    <strong>About Me:</strong>
                    <?= nl2br(e($tutorProfile['bio'] ?? 'Not provided')) ?>
                </p>

                <p>
                    <strong>Experience:</strong>
                    <?= nl2br(e($tutorProfile['experience'] ?? 'Not provided')) ?>
                </p>

                <p>
                    <strong>Profile Status:</strong>
                    <?= (int)$tutorProfile['is_active'] === 1
                        ? 'Active'
                        : 'Inactive' ?>
                </p>

            <?php else: ?>

                <p>No tutor profile found for this account.</p>

            <?php endif; ?>
        </section>

    <?php endif; ?>

</main>

</body>
</html>
