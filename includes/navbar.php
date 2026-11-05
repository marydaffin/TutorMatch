
<?php
// Start the session if it hasn't already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if a user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

// Get the logged-in user's first name
$firstName = $_SESSION['first_name'] ?? '';

// Generate a secure logout token
if ($isLoggedIn && empty($_SESSION['logout_csrf'])) {
    $_SESSION['logout_csrf'] = bin2hex(random_bytes(32));
}
?>

<!-- TutorMatch Navigation Bar -->
<header class="navbar">
    <div class="nav-container">

        <!-- Website Title - Left Side -->
        <h2 class="nav-title">
            <a href="index.php" class="logo">
                TutorMatch
            </a>
        </h2>

        <!-- Navigation Links - Right Side -->
        <nav class="nav-links">

            <a href="index.php">Home</a>

            <a href="tutors.php">Find Tutors</a>

            <?php if ($isLoggedIn): ?>

                <!-- Only shown when logged in -->

                <a href="dashboard.php">Dashboard</a>

                <span class="welcome-user">
                    Hi, <?= htmlspecialchars(
                        $firstName,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>!
                </span>

                <!-- Secure Logout Button -->
                <form
                    action="logout.php"
                    method="POST"
                    class="logout-form"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            $_SESSION['logout_csrf'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                    <button
                        type="submit"
                        class="logout-btn"
                    >
                        Logout
                    </button>
                </form>

            <?php else: ?>

                <!-- Only shown when logged out -->

                <a href="login.php">Login</a>

                <a href="register.php">Sign Up</a>

            <?php endif; ?>

        </nav>

    </div>
</header>
