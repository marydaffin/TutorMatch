
<?php
session_start();
require_once 'db.php';

// Use the existing PDO connection from db.php
$db = $conn ?? $pdo ?? null;

if (!$db instanceof PDO) {
    die("Database connection error. Check db.php.");
}

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['registration_csrf'])) {
    $_SESSION['registration_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$success = isset($_GET['success'])
    ? "Account created successfully! You can now log in."
    : '';

$first_name = '';
$last_name = '';
$email = '';
$role = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['registration_csrf'], $token)) {
        $error = "Invalid form submission. Refresh the page.";
    }
    elseif (
        $first_name === '' ||
        $last_name === '' ||
        $email === '' ||
        $password === '' ||
        $confirm_password === '' ||
        $role === ''
    ) {
        $error = "Please fill out all fields.";
    }
    elseif (strlen($first_name) > 50 ||
            strlen($last_name) > 50) {
        $error = "Names cannot exceed 50 characters.";
    }
    elseif (strlen($email) > 100 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    }
    elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    }
    elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    }
    elseif (!in_array($role, ['Student', 'Tutor', 'Both'], true)) {
        $error = "Please select a valid account type.";
    }
    else {

        try {
            // Check whether email already exists
            $check = $db->prepare(
                "SELECT user_id FROM users WHERE email = ? LIMIT 1"
            );
            $check->execute([$email]);

            if ($check->fetch()) {
                $error = "An account with this email already exists.";
            }
            else {

                // Hash password before storing it
                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($role === 'Student') {

                    // Student accounts only need the users table
                    $stmt = $db->prepare(
                        "INSERT INTO users
                        (first_name, last_name, email, password_hash, role)
                        VALUES (?, ?, ?, ?, ?)"
                    );

                    $stmt->execute([
                        $first_name,
                        $last_name,
                        $email,
                        $password_hash,
                        $role
                    ]);

                    unset($_SESSION['pending_registration']);

                    header("Location: register.php?success=1");
                    exit;

                } else {

                    // Tutor or Both:
                    // Temporarily hold information in the session
                    // until the additional profile is completed.
                    $_SESSION['pending_registration'] = [
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'email' => $email,
                        'password_hash' => $password_hash,
                        'role' => $role,
                        'created_time' => time()
                    ];

                    header("Location: tutor_setup.php");
                    exit;
                }
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = "Registration failed. Please try again.";
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

    <title>Register - TutorMatch</title>

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

<main class="register-container">

    <h1>Create an Account</h1>
    <p>Join TutorMatch and start learning today!</p>

    <?php if ($error): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p class="success"><?= e($success) ?></p>
        <p>
            <a href="login.php">Go to Login</a>
        </p>
    <?php endif; ?>

    <form action="register.php" method="POST">

        <input type="hidden" name="csrf_token"
               value="<?= e($_SESSION['registration_csrf']) ?>">

        <label for="first_name">First Name</label>
        <input
            type="text"
            id="first_name"
            name="first_name"
            placeholder="Enter your first name"
            value="<?= e($first_name) ?>"
            maxlength="50"
            required
        >

        <label for="last_name">Last Name</label>
        <input
            type="text"
            id="last_name"
            name="last_name"
            placeholder="Enter your last name"
            value="<?= e($last_name) ?>"
            maxlength="50"
            required
        >

        <label for="email">Email</label>
        <input
            type="email"
            id="email"
            name="email"
            placeholder="Enter your email"
            value="<?= e($email) ?>"
            maxlength="100"
            required
        >

        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            placeholder="Create a password"
            minlength="8"
            required
        >

        <label for="confirm_password">Confirm Password</label>
        <input
            type="password"
            id="confirm_password"
            name="confirm_password"
            placeholder="Confirm your password"
            minlength="8"
            required
        >

        <label for="role">Account Type</label>
        <select id="role" name="role" required>
            <option value="">Select account type</option>

            <option value="Student"
                <?= $role === 'Student' ? 'selected' : '' ?>>
                Student
            </option>

            <option value="Tutor"
                <?= $role === 'Tutor' ? 'selected' : '' ?>>
                Tutor
            </option>

            <option value="Both"
                <?= $role === 'Both' ? 'selected' : '' ?>>
                Both (Student & Tutor)
            </option>
        </select>

        <button type="submit">Continue</button>
    </form>

    <p class="login-link">
        Already have an account?
        <a href="login.php">Login here</a>
    </p>

</main>

</body>
</html>
