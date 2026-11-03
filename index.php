
<?php
require_once "db.php";

// Get search input
$search = trim($_GET['search'] ?? "");

// Get active tutors from the database
$sql = "
    SELECT
        u.user_id,
        u.first_name,
        u.last_name,
        tp.major,
        tp.bio,
        tp.hourly_rate,
        GROUP_CONCAT(DISTINCT s.subject_name
            ORDER BY s.subject_name SEPARATOR ', ') AS subjects
    FROM Users u
    JOIN Tutor_Profiles tp
        ON u.user_id = tp.tutor_id
    LEFT JOIN Tutor_Subjects ts
        ON tp.tutor_id = ts.tutor_id
    LEFT JOIN Subjects s
        ON ts.subject_id = s.subject_id
    WHERE tp.is_active = TRUE
      AND (
          :search_empty = ''
          OR EXISTS (
              SELECT 1
              FROM Tutor_Subjects ts2
              JOIN Subjects s2
                  ON ts2.subject_id = s2.subject_id
              WHERE ts2.tutor_id = tp.tutor_id
                AND s2.subject_name LIKE :search_subject
          )
      )
    GROUP BY
        u.user_id,
        u.first_name,
        u.last_name,
        tp.major,
        tp.bio,
        tp.hourly_rate
    ORDER BY u.first_name
";

$stmt = $conn->prepare($sql);

$stmt->execute([
    ':search_empty' => $search,
    ':search_subject' => "%" . $search . "%"
]);

$tutors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>TutorMatch - Home</title>
    <link rel="stylesheet" href="css/styles.css">
</head>

<body>

    <!-- Navigation Bar -->
    <header>
        <nav class="navbar">
            <h2>TutorMatch</h2>

            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="#tutors">Find Tutors</a>
                <a href="login.php">Login</a>
                <a href="register.php">Sign Up</a>
            </div>
        </nav>
    </header>

    <!-- Welcome Section -->
    <section class="hero">
        <h1>Welcome to TutorMatch</h1>

        <p>
            Find the right tutor for your academic needs.
            Search subjects, explore tutors, and start learning!
        </p>

        <a href="#tutors" class="btn">Find a Tutor</a>
    </section>

    <!-- Search Section -->
    <section class="search-section">
        <h2>Search for Tutors</h2>

        <form action="index.php" method="GET">
            <input
                type="text"
                name="search"
                placeholder="Search by subject..."
                value="<?php echo htmlspecialchars($search); ?>"
            >

            <button type="submit">Search</button>
        </form>
    </section>

    <!-- Tutor Listings -->
    <section id="tutors" class="tutor-section">
        <h2>Available Tutors</h2>

        <div class="tutor-container">

            <?php if (count($tutors) > 0): ?>

                <?php foreach ($tutors as $tutor): ?>

                    <div class="tutor-card">

                        <h3>
                            <?php
                            echo htmlspecialchars(
                                $tutor['first_name'] . " " .
                                $tutor['last_name']
                            );
                            ?>
                        </h3>

                        <p>
                            <strong>Major:</strong>
                            <?php
                            echo htmlspecialchars(
                                $tutor['major'] ?? 'Not listed'
                            );
                            ?>
                        </p>

                        <p>
                            <strong>Subjects:</strong>
                            <?php
                            echo htmlspecialchars(
                                $tutor['subjects'] ?? 'Not listed'
                            );
                            ?>
                        </p>

                        <p>
                            <strong>About:</strong>
                            <?php
                            echo htmlspecialchars(
                                $tutor['bio'] ?? 'No bio available'
                            );
                            ?>
                        </p>

                        <p>
                            <strong>Rate:</strong>
                            $<?php
                            echo number_format(
                                (float)$tutor['hourly_rate'], 2
                            );
                            ?>/hr
                        </p>

                        <a href="tutor_profile.php?id=<?php
                            echo (int)$tutor['user_id'];
                        ?>" class="btn">
                            View Profile
                        </a>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p>No tutors found.</p>

            <?php endif; ?>

        </div>
    </section>

    <!-- Footer -->
    <footer>
        <p>&copy; <?php echo date("Y"); ?> TutorMatch</p>
    </footer>

</body>
</html>
