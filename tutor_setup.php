
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

// Logged-in users cannot register again
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Get information saved by register.php
$pending = $_SESSION['pending_registration'] ?? null;

if (
    !$pending ||
    !in_array($pending['role'] ?? '', ['Tutor', 'Both'], true)
) {
    header("Location: register.php");
    exit;
}

// Expire unfinished registrations after 30 minutes
if (time() - ($pending['created_time'] ?? 0) > 1800) {
    unset($_SESSION['pending_registration']);
    header("Location: register.php");
    exit;
}

// Create form security token
if (empty($_SESSION['registration_csrf'])) {
    $_SESSION['registration_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$bio = '';
$major = '';
$hourly_rate = '';
$experience = '';
$selected_subjects = [];
$subjects = [];

// Load all subjects from the subjects table
try {
    $stmt = $db->query(
        "SELECT subject_id, subject_name
         FROM subjects
         ORDER BY subject_name ASC"
    );

    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = "Unable to load subjects from the database.";
}

// Handle submitted form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {

    $bio = trim($_POST['bio'] ?? '');
    $major = trim($_POST['major'] ?? '');
    $hourly_rate = trim($_POST['hourly_rate'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $token = $_POST['csrf_token'] ?? '';
    $selected_subjects = $_POST['subjects'] ?? [];

    // Validate form
    if (!hash_equals($_SESSION['registration_csrf'], $token)) {
        $error = "Invalid form submission. Please refresh.";
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
        !preg_match('/^\d{1,6}(\.\d{1,2})?$/', $hourly_rate)
        || (float)$hourly_rate <= 0
    ) {
        $error = "Please enter a valid hourly rate.";
    }
    elseif (
        !is_array($selected_subjects) ||
        count($selected_subjects) === 0
    ) {
        $error = "Please select at least one subject.";
    }
    else {

        // Check selected subjects against actual database IDs
        $valid_ids = array_map(
            'intval',
            array_column($subjects, 'subject_id')
        );

        $subject_ids = [];
        $invalid = false;

        foreach ($selected_subjects as $id) {

            if (
                !is_scalar($id) ||
                !ctype_digit((string)$id) ||
                (int)$id <= 0 ||
                !in_array((int)$id, $valid_ids, true)
            ) {
                $invalid = true;
                break;
            }

            $subject_ids[] = (int)$id;
        }

        // Avoid inserting duplicate subject selections
        $subject_ids = array_values(
            array_unique($subject_ids)
        );

        if ($invalid || empty($subject_ids)) {
            $error = "Please select valid subjects.";
        }
        else {

            try {
                // All inserts must succeed together
                $db->beginTransaction();

                // Check that the email is not registered
                $check = $db->prepare(
                    "SELECT user_id
                     FROM users
                     WHERE email = ?
                     LIMIT 1"
                );

                $check->execute([$pending['email']]);

                if ($check->fetch()) {
                    throw new RuntimeException(
                        "An account with this email already exists."
                    );
                }

                // 1. Create account in users table
                $stmt = $db->prepare(
                    "INSERT INTO users
                    (first_name, last_name, email,
                     password_hash, role)
                    VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $pending['first_name'],
                    $pending['last_name'],
                    $pending['email'],
                    $pending['password_hash'],
                    $pending['role']
                ]);

                // Get generated user ID
                $user_id = (int)$db->lastInsertId();

                // 2. Create tutor profile
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

                // 3. Connect tutor to selected subjects
                $stmt = $db->prepare(
                    "INSERT INTO tutor_subjects
                    (tutor_id, subject_id)
                    VALUES (?, ?)"
                );

                foreach ($subject_ids as $subject_id) {
                    $stmt->execute([
                        $user_id,
                        $subject_id
                    ]);
                }

                // Save all records together
                $db->commit();

                // Clear unfinished registration
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

                $error = "Unable to create your tutor account.";
            }
        }
    }
}

// Preserve checked subjects after a validation error
$checked_subjects = is_array($selected_subjects)
    ? array_map('strval', array_filter(
        $selected_subjects,
        'is_scalar'
    ))
    : [];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Tutor Setup - TutorMatch</title>

    <link rel="stylesheet" href="css/styles.css?v=11">
    <link rel="stylesheet" href="css/subjects.css?v=1">
</head>

<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="register-container tutor-setup-container">

    <div class="step-label">Step 2 of 2</div>

    <h1>Complete Your Tutor Profile</h1>

    <p>
        Tell students about yourself and the subjects
        you can help them with.
    </p>

    <p class="account-type">
        Account Type:
        <strong><?= e($pending['role']) ?></strong>
    </p>

    <?php if ($error): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <form action="tutor_setup.php" method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION['registration_csrf']) ?>"
        >

        <!-- Major -->
        <label for="major">
            Major / Field of Study *
        </label>

        <input
            type="text"
            id="major"
            name="major"
            placeholder="e.g. Computer Science"
            maxlength="100"
            value="<?= e($major) ?>"
            required
        >

        <!-- Hourly Rate -->
        <label for="hourly_rate">
            Hourly Rate ($) *
        </label>

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

        <!-- Subject Selection -->
        <div class="subject-field">

            <label class="subject-heading">
                Subjects I Tutor *
            </label>

            <p class="subject-help">
                Select all the subjects you are able to tutor.
            </p>

            <div class="subjects-container">

                <?php if (empty($subjects)): ?>

                    <p class="empty-subjects">
                        No subjects are currently available.
                    </p>

                <?php else: ?>

                    <?php foreach ($subjects as $subject): ?>

                        <label class="subject-option">

                            <input
                                type="checkbox"
                                name="subjects[]"
                                value="<?= (int)$subject['subject_id'] ?>"
                                <?= in_array(
                                    (string)$subject['subject_id'],
                                    $checked_subjects,
                                    true
                                ) ? 'checked' : '' ?>
                            >

                            <span>
                                <?= e($subject['subject_name']) ?>
                            </span>

                        </label>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

        <!-- About Me -->
        <label for="bio">About Me</label>

        <textarea
            id="bio"
            name="bio"
            rows="5"
            maxlength="2000"
            placeholder="Introduce yourself to students..."
        ><?= e($bio) ?></textarea>

        <!-- Experience -->
        <label for="experience">
            Tutoring Experience
        </label>

        <textarea
            id="experience"
            name="experience"
            rows="5"
            maxlength="3000"
            placeholder="Describe your tutoring experience..."
        ><?= e($experience) ?></textarea>

        <!-- Submit -->
        <button
            type="submit"
            <?= empty($subjects) ? 'disabled' : '' ?>
        >
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
