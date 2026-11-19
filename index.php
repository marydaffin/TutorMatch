
<?php
session_start();
require_once 'db.php';

// Use your existing PDO connection
$db = $conn ?? $pdo ?? null;

if (!$db instanceof PDO) {
    die("Database connection error. Check db.php.");
}

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Read search filters
$search = trim($_GET['search'] ?? '');

$subject_id = filter_input(
    INPUT_GET,
    'subject_id',
    FILTER_VALIDATE_INT
);

if (!$subject_id || $subject_id <= 0) {
    $subject_id = null;
}

$tutors = [];
$subjects = [];
$error = '';

// Load subjects for the dropdown
try {
    $stmt = $db->query(
        "SELECT subject_id, subject_name
         FROM subjects
         ORDER BY subject_name ASC"
    );

    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = "Unable to load subjects.";
}

// Retrieve tutors
try {

    $sql = "
        SELECT
            u.user_id,
            u.first_name,
            u.last_name,
            u.role,
            t.bio,
            t.major,
            t.hourly_rate,
            t.experience,

            (
                SELECT GROUP_CONCAT(
                    s.subject_name
                    ORDER BY s.subject_name
                    SEPARATOR ', '
                )
                FROM tutor_subjects ts
                INNER JOIN subjects s
                    ON ts.subject_id = s.subject_id
                WHERE ts.tutor_id = u.user_id
            ) AS subject_names

        FROM users u

        INNER JOIN tutor_profiles t
            ON u.user_id = t.tutor_id

        WHERE u.role IN ('Tutor', 'Both')
          AND t.is_active = 1
    ";

    $params = [];

    // Search by name, major, or subject name
    if ($search !== '') {

        $sql .= "
            AND (
                u.first_name LIKE :search_first
                OR u.last_name LIKE :search_last
                OR CONCAT(
                    u.first_name,
                    ' ',
                    u.last_name
                ) LIKE :search_full
                OR t.major LIKE :search_major

                OR EXISTS (
                    SELECT 1
                    FROM tutor_subjects ts2
                    INNER JOIN subjects s2
                        ON ts2.subject_id = s2.subject_id
                    WHERE ts2.tutor_id = u.user_id
                      AND s2.subject_name LIKE :search_subject
                )
            )
        ";

        $value = '%' . $search . '%';

        $params['search_first'] = $value;
        $params['search_last'] = $value;
        $params['search_full'] = $value;
        $params['search_major'] = $value;
        $params['search_subject'] = $value;
    }

    // Filter by selected subject
    if ($subject_id !== null) {

        $sql .= "
            AND EXISTS (
                SELECT 1
                FROM tutor_subjects ts3
                WHERE ts3.tutor_id = u.user_id
                  AND ts3.subject_id = :subject_id
            )
        ";

        $params['subject_id'] = $subject_id;
    }

    $sql .= "
        ORDER BY
            u.first_name ASC,
            u.last_name ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

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

    <title>TutorMatch - Find a Tutor</title>

    <link rel="stylesheet" href="css/styles.css?v=11">
    <link rel="stylesheet" href="css/subjects.css?v=1">
</head>

<body>

<!-- Shared navigation -->
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

<!-- Search Section -->
<section class="search-section" id="find-tutors">

    <h2>Search for Tutors</h2>

    <form
        action="index.php#find-tutors"
        method="GET"
        class="search-form"
    >

        <!-- Text Search -->
        <input
            type="text"
            name="search"
            placeholder="Search by name, major, or subject..."
            value="<?= e($search) ?>"
            maxlength="100"
        >

        <!-- Subject Dropdown -->
        <select
            name="subject_id"
            class="subject-filter"
            aria-label="Filter by subject"
        >

            <option value="">All Subjects</option>

            <?php foreach ($subjects as $subject): ?>

                <option
                    value="<?= (int)$subject['subject_id'] ?>"
                    <?= $subject_id === (int)$subject['subject_id']
                        ? 'selected'
                        : '' ?>
                >
                    <?= e($subject['subject_name']) ?>
                </option>

            <?php endforeach; ?>

        </select>

        <button type="submit">
            Search
        </button>

        <?php if ($search !== '' || $subject_id !== null): ?>

            <a
                href="index.php#find-tutors"
                class="clear-search"
            >
                Clear
            </a>

        <?php endif; ?>

    </form>

</section>

<!-- Available Tutors -->
<section class="tutors-section">

    <h2>Available Tutors</h2>

    <?php if ($error): ?>

        <p class="error"><?= e($error) ?></p>

    <?php elseif (empty($tutors)): ?>

        <p class="no-tutors">
            No tutors found matching your search.
        </p>

        <?php if ($search !== '' || $subject_id !== null): ?>

            <a href="index.php#find-tutors" class="clear-search">
                View All Tutors
            </a>

        <?php endif; ?>

    <?php else: ?>

        <div class="tutor-grid">

            <?php foreach ($tutors as $tutor): ?>

                <div class="tutor-card">

                    <!-- Tutor Name -->
                    <h3>
                        <?= e(
                            $tutor['first_name'] . ' ' .
                            $tutor['last_name']
                        ) ?>
                    </h3>

                    <!-- Major -->
                    <p>
                        <strong>Major:</strong>
                        <?= e($tutor['major'] ?? 'Not provided') ?>
                    </p>

                    <!-- Hourly Rate -->
                    <p>
                        <strong>Hourly Rate:</strong>
                        $<?= number_format(
                            (float)$tutor['hourly_rate'],
                            2
                        ) ?>/hr
                    </p>

                    <!-- Subjects -->
                    <p>
                        <strong>Subjects:</strong>

                        <?= e(
                            $tutor['subject_names']
                            ?: 'Not listed yet'
                        ) ?>
                    </p>

                    <!-- Bio -->
                    <?php if (!empty($tutor['bio'])): ?>

                        <p class="tutor-bio">
                            <?= e($tutor['bio']) ?>
                        </p>

                    <?php endif; ?>

                    <!-- Specific Tutor Profile -->
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
