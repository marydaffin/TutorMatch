
<?php
session_start();
require_once 'db.php';

$db = $conn ?? $pdo ?? null;

if (!$db instanceof PDO) {
    die("Database connection error. Check db.php.");
}

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['login_csrf'], $token)) {
        $error = "Invalid form submission.";
    }
    elseif ($email === '' || $password === '') {
        $error = "Please enter your email and password.";
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    }
    else {
        try {
            $stmt = $db->prepare(
                "SELECT user_id, first_name, last_name,
                        email, password_hash, role
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                $user &&
                password_verify($password, $user['password_hash'])
            ) {
                session_regenerate_id(true);
                $_SESSION['logout_csrf'] = bin2hex(random_bytes(32));

                $_SESSION['user_id'] = (int)$user['user_id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                $_SESSION['logout_csrf'] = bin2hex(
                    random_bytes(32)
                );

                header("Location: dashboard.php");
                exit;

            } else {
                $error = "Incorrect email or password.";
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = "Unable to log in. Please try again.";
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
    <title>Login - TutorMatch</title>
    <link rel="stylesheet" href="css/styles.css?v=5">
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="login-container">

    <h1>Welcome Back!</h1>
    <p>Log in to continue using TutorMatch.</p>

    <?php if ($error): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <form action="login.php" method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION['login_csrf']) ?>"
        >

        <label for="email">Email Address</label>

        <input
            type="email"
            id="email"
            name="email"
            placeholder="Enter your email"
            value="<?= e($email) ?>"
            maxlength="100"
            autocomplete="email"
            required
        >

        <label for="password">Password</label>

        <input
            type="password"
            id="password"
            name="password"
            placeholder="Enter your password"
            autocomplete="current-password"
            required
        >

        <button type="submit">Login</button>

    </form>

    <p class="signup-link">
        Don't have an account?
        <a href="register.php">Sign up here</a>
    </p>

</main>

</body>
</html>
