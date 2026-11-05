
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

// Only allow users who started Tutor/Both registration
$pending = $_SESSION['pending_registration'] ?? null;

if (
    !$pending ||
    !in_array($pending['role'] ?? '', ['Tutor', 'Both'], true)
) {
    header("Location: register.php");
    exit;
}

// Pending registration expires after 30 minutes
if (time() - ($pending['created_time'] ?? 0) > 1800) {
    unset($_SESSION['pending_registration']);
    header("Location: register.php");
    exit;
}

if (empty($_SESSION['registration_csrf'])) {
    $_SESSION['registration_csrf'] = bin2hex(random_bytes(32));
}

$error = '';

$bio = '';
$major = '';
$hourly_rate = '';
$experience = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $bio = trim($_POST['bio'] ?? '');
    $major = trim($_POST['major'] ?? '');
    $hourly_rate = trim($_POST['hourly_rate'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['registration_csrf'], $token)) {
        $error = "Invalid form submission. Refresh the page.";
    }
    elseif ($major === '' || $hourly_rate === '') {
        $error = "Please enter your major and hourly rate.";
    }
    elseif (strlen($major) > 100) {
        $error = "Major cannot exceed 100 characters.";
    }
    elseif (strlen($bio) > 2000 ||
            strlen($experience) > 3000) {
        $error = "Bio or experience is too long.";
    }
    elseif (
        !preg_match(
            '/^\d{1,6}(\.\d{1,2})?$/',
            $hourly_rate
        )
        || (float)$hourly_rate <= 0
    ) {
        $error = "Enter a valid hourly rate greater than zero.";
    }
    else {

        try {
            // Begin transaction:
            // Both database records must succeed together.
            $db->beginTransaction();

            // Ensure email is still available
            $check = $db->prepare(
                "SELECT user_id FROM users WHERE email = ? LIMIT 1"
            );
            $check->execute([$pending['email']]);

            if ($check->fetch()) {
                throw new RuntimeException(
                    "An account with this email already exists."
                );
            }

            // Step 1: Create account in users table
            $stmt = $db->prepare(
                "INSERT INTO users
                (first_name, last_name, email, password_hash, role)
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $pending['first_name'],
                $pending['last_name'],
                $pending['email'],
                $pending['password_hash'],
                $pending['role']
            ]);

            // Step 2: Get the ID of the new account
            $user_id = (int)$db->lastInsertId();

            // Step 3: Create tutor profile with same ID
            $stmt = $db->prepare(
                "INSERT INTO tutor_profiles
                (tutor_id, bio, major, hourly_rate,
                 experience, is_active)
                VALUES (?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $user_id,
                $bio !== '' ? $bio : null,
                $major,
                $hourly_rate,
                $experience !== '' ? $experience : null,
                1
            ]);

            // Both records saved successfully
            $db->commit();

            // Clear temporary registration data
            unset($_SESSION['pending_registration']);

            header("Location: register.php?success=1");
            exit;

        } catch (RuntimeException $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $error = $e->getMessage();

        } catch (PDOException $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log($e->getMessage());

            if ($e->getCode() === '23000') {
                $error = "This email may already be registered, or the profile could not be saved.";
            } else {
                $error = "Unable to create tutor profile. Please try again.";
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

    <title>Tutor Profile Setup - TutorMatch</title>

    <link rel="stylesheet" href="css/styles.css?v=3">
</head>
<body>

<header class="navbar">
    <div class="nav-container">
        <h2>TutorMatch</h2>

        <nav>
            <a href="index.php">Home</a>
            <a href="tutors.php">Find Tutors</a>
            <a href="login.php">Login</a>
            <a href="register.php">Sign Up</a>
        </nav>
    </div>
</header>

<main class="register-container tutor-setup-container">

    <div class="step-label">Step 2 of 2</div>

    <h1>Complete Your Tutor Profile</h1>

    <p>
        Tell students a little about yourself and
        the subjects you can help them with.
    </p>

    <p class="account-type">
        Account Type:
        <strong><?= e($pending['role']) ?></strong>
    </p>

    <?php if ($error): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <form action="tutor_setup.php" method="POST">

        <input type="hidden" name="csrf_token"
               value="<?= e($_SESSION['registration_csrf']) ?>">

        <!-- Major -->
        <label for="major">Major / Field of Study *</label>

        <input
            type="text"
            id="major"
            name="major"
            placeholder="e.g. Computer Science"
            value="<?= e($major) ?>"
            maxlength="100"
            required
        >

        <!-- Hourly Rate -->
        <label for="hourly_rate">Hourly Rate ($) *</label>

        <input
            type="number"
            id="hourly_rate"
            name="hourly_rate"
            placeholder="e.g. 25.00"
            min="0.01"
            max="999999.99"
            step="0.01"
            value="<?= e($hourly_rate) ?>"
            required
        >

        <!-- Bio -->
        <label for="bio">About Me</label>

        <textarea
            id="bio"
            name="bio"
            rows="5"
            maxlength="2000"
            placeholder="Introduce yourself to students..."
        ><?= e($bio) ?></textarea>

        <!-- Experience -->
        <label for="experience">Tutoring Experience</label>

        <textarea
            id="experience"
            name="experience"
            rows="5"
            maxlength="3000"
            placeholder="Describe your experience helping students..."
        ><?= e($experience) ?></textarea>

        <button type="submit">
            Complete Registration
        </button>

    </form>

    <p class="login-link">
        <a href="register.php">
            Back to Registration
        </a>
    </p>

</main>

</body>
</html>
