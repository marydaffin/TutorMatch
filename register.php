
<?php
session_start();
require_once 'db.php';

// Supports a PDO connection named $conn or $pdo
$db = $conn ?? $pdo ?? null;

if (!$db instanceof PDO) {
    die("Database connection error. Check db.php.");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get form information
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';

    // Validate information
    if (
        $first_name === '' ||
        $last_name === '' ||
        $email === '' ||
        $password === '' ||
        $confirm_password === '' ||
        $role === ''
    ) {
        $error = "Please fill out all fields.";
    }
    elseif (
        strlen($first_name) > 50 ||
        strlen($last_name) > 50
    ) {
        $error = "Names cannot exceed 50 characters.";
    }
    elseif (
        strlen($email) > 100 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
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
            // Check if email already exists
            $check = $db->prepare(
                "SELECT user_id FROM users WHERE email = ? LIMIT 1"
            );

            $check->execute([$email]);

            if ($check->fetch(PDO::FETCH_ASSOC)) {
                $error = "An account with this email already exists.";
            }
            else {

                // Encrypt password using secure hashing
                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Insert the user into the database
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

                $success = "Account created successfully! You can now log in.";
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sign Up - TutorMatch</title>

    <link rel="stylesheet" href="css/styles.css?v=2">
</head>

<body>

<!-- Navigation Bar -->
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

<!-- Registration Form -->
<main class="register-container">

    <h1>Create an Account</h1>
    <p>Join TutorMatch and start learning today!</p>

    <!-- Error Message -->
    <?php if ($error): ?>
        <p class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <!-- Success Message -->
    <?php if ($success): ?>
        <p class="success">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form action="register.php" method="POST">

        <!-- First Name -->
        <label for="first_name">First Name</label>
        <input
            type="text"
            id="first_name"
            name="first_name"
            placeholder="Enter your first name"
            maxlength="50"
            required
        >

        <!-- Last Name -->
        <label for="last_name">Last Name</label>
        <input
            type="text"
            id="last_name"
            name="last_name"
            placeholder="Enter your last name"
            maxlength="50"
            required
        >

        <!-- Email -->
        <label for="email">Email</label>
        <input
            type="email"
            id="email"
            name="email"
            placeholder="Enter your email"
            maxlength="100"
            required
        >

        <!-- Password -->
        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            placeholder="Create a password"
            minlength="8"
            required
        >

        <!-- Confirm Password -->
        <label for="confirm_password">Confirm Password</label>
        <input
            type="password"
            id="confirm_password"
            name="confirm_password"
            placeholder="Confirm your password"
            minlength="8"
            required
        >

        <!-- Account Type -->
        <label for="role">Account Type</label>
        <select id="role" name="role" required>
            <option value="">Select account type</option>
            <option value="Student">Student</option>
            <option value="Tutor">Tutor</option>
            <option value="Both">Both</option>
        </select>

        <!-- Submit Button -->
        <button type="submit">Sign Up</button>

    </form>

    <p class="login-link">
        Already have an account?
        <a href="login.php">Login here</a>
    </p>

</main>

</body>
</html>
