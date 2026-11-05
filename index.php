
<?php
session_start();
require_once 'db.php';

// Use the existing PDO connection
$db = $conn ?? $pdo ?? null;

if (!$db instanceof PDO) {
    die("Database connection error. Check db.php.");
}

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Protect output against HTML injection
function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Get search input
$search = trim($_GET['search'] ?? '');
$tutors = [];
$error = '';

try {

    // Retrieve active tutors from the database
    $sql = "
        SELECT
            u.user_id,
            u.first_name,
            u.last_name,
            u.role,
            t.bio,
            t.major,
            t.hourly_rate,
            t.experience
        FROM users u
        INNER JOIN tutor_profiles t
            ON u.user_id = t.tutor_id
        WHERE
            u.role IN ('Tutor', 'Both')
            AND t.is_active = 1
    ";

    // Filter by major, first name, or last name
    if ($search !== '') {
        $sql .= "
            AND (
                t.major LIKE :search_major
                OR u.first_name LIKE :search_first
                OR u.last_name LIKE :search_last
            )
        ";
    }

    $sql .= " ORDER BY u.first_name ASC, u.last_name ASC";

    $stmt = $db->prepare($sql);

    if ($search !== '') {
        $searchValue = '%' . $search . '%';

        $stmt->execute([
            'search_major' => $searchValue,
            'search_first' => $searchValue,
            'search_last' => $searchValue
        ]);
    } else {
        $stmt->execute();
    }

    $tutors = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = "Unable to load tutors right now.";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>TutorMatch - Find Your Perfect Tutor</title>

    <link rel="stylesheet" href="css/styles.css?v=8">
</head>

<body>

<!-- Shared navigation bar -->
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<!-- Welcome Section -->
<section class="hero">

    <h1>Welcome to TutorMatch</h1>

    <p>
        Find the right tutor for your academic needs.
        Search subjects, explore tutors, and start learning!
    </p>

    <a href="#find-tutors" class="btn">
        Find a Tutor
    </a>

</section>

<!-- Tutor Search Section -->
<section class="search-section" id="find-tutors">

    <h2>Search for Tutors</h2>

    <form action="index.php#find-tutors" method="GET"
          class="search-form">

        <input
            type="text"
            name="search"
            placeholder="Search by major or tutor name..."
            value="<?= e($search) ?>"
            maxlength="100"
        >

        <button type="submit">
            Search
        </button>

    </form>

</section>

<!-- Available Tutors Section -->
<section class="tutors-section">

    <h2>Available Tutors</h2>

    <?php if ($error): ?>

        <p class="error">
            <?= e($error) ?>
        </p>

    <?php elseif (empty($tutors)): ?>

        <?php if ($search !== ''): ?>

            <p class="no-tutors">
                No tutors found matching
                "<?= e($search) ?>".
            </p>

            <a href="index.php#find-tutors" class="clear-search">
                Clear Search
            </a>

        <?php else: ?>

            <p class="no-tutors">
                No tutors found.
            </p>

        <?php endif; ?>

    <?php else: ?>

        <div class="tutor-grid">

            <?php foreach ($tutors as $tutor): ?>

                <div class="tutor-card">

                    <h3>
                        <?= e(
                            $tutor['first_name'] . ' ' .
                            $tutor['last_name']
                        ) ?>
                    </h3>

                    <p>
                        <strong>Major:</strong>
                        <?= e($tutor['major'] ?? 'Not provided') ?>
                    </p>

                    <p>
                        <strong>Hourly Rate:</strong>
                        $<?= number_format(
                            (float)$tutor['hourly_rate'],
                            2
                        ) ?>/hr
                    </p>

                    <?php if (!empty($tutor['bio'])): ?>

                        <p class="tutor-bio">
                            <?= e($tutor['bio']) ?>
                        </p>

                    <?php endif; ?>

                    <a
                        href="tutor_profile.php?id=<?= (int)$tutor['user_id'] ?>"
                        class="view-profile-btn"
                    >
                        View Profile
                    </a>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>

</body>
</html>
