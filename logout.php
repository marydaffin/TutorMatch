
<?php
session_start();

// Logout must come from the POST form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method not allowed.");
}

// Get security tokens
$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['logout_csrf'] ?? '';

// Verify the logout request
if (
    !isset($_SESSION['user_id']) ||
    $submittedToken === '' ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    exit("Invalid logout request.");
}

// Clear session variables
$_SESSION = [];

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy session
session_destroy();

// Redirect to homepage
header("Location: index.php");
exit;
?>
