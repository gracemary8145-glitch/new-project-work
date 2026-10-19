
<?php
require_once "session_config.php";
require_once "db_connect.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: login.php");
    exit;
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$error = "";
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        $error = "Invalid request. Refresh the page and try again.";
    } else {
        $code = strtoupper(trim($_POST["course_code"] ?? ""));
        $title = trim($_POST["course_title"] ?? "");
        $lecturerId = filter_var(
            $_POST["lecturer_id"] ?? "",
            FILTER_VALIDATE_INT
        );

        if ($code === "" || $title === "") {
            $error = "Course code and course title are required.";
        } elseif (strlen($code) > 20 || strlen($title) > 150) {
            $error = "Course code or title is too long.";
        } elseif (!preg_match('/\A[A-Z0-9-]+\z/', $code)) {
            $error = "Use only letters, numbers and hyphens in the course code.";
        } elseif (!$lecturerId || $lecturerId < 1) {
            $error = "Please select a lecturer.";
        } else {
            // Verify that the selected account is actually a lecturer.
            $check = $conn->prepare(
                "SELECT id FROM users WHERE id = ? AND role = 'lecturer'"
            );
            $check->bind_param("i", $lecturerId);
            $check->execute();
            $validLecturer = $check->get_result()->num_rows > 0;
            $check->close();

            if (!$validLecturer) {
                $error = "The selected lecturer is invalid.";
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO courses (course_code, course_title, lecturer_id)
                     VALUES (?, ?, ?)"
                );
                $stmt->bind_param("ssi", $code, $title, $lecturerId);

                if ($stmt->execute()) {
                    header("Location: courses.php?msg=added");
                    exit;
                } elseif ($stmt->errno === 1062) {
                    $error = "That course code already exists.";
                } else {
                    $error = "Could not save the course.";
                    error_log("Course creation failed: " . $stmt->error);
                }
                $stmt->close();
            }
        }
    }
}

if (($_GET["msg"] ?? "") === "added") {
    $message = "Course added successfully.";
}

$lecturers = $conn->query(
    "SELECT id, full_name FROM users
     WHERE role = 'lecturer' ORDER BY full_name"
);

$courses = $conn->query(
    "SELECT c.id, c.course_code, c.course_title,
            u.full_name AS lecturer_name
     FROM courses c
     LEFT JOIN users u ON c.lecturer_id = u.id
     ORDER BY c.course_code"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Course Management</title>
    
<style>
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background: #f3f7f4;
        color: #1f2937;
        line-height: 1.6;
    }

    header {
        background: #14532d;
        color: white;
        padding: 24px 5%;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.12);
    }

    header h1 {
        margin: 0 0 8px;
        font-size: 26px;
    }

    .back {
        display: inline-block;
        color: white;
        text-decoration: none;
        font-size: 14px;
    }

    .back:hover {
        text-decoration: underline;
    }

    main {
        width: 92%;
        max-width: 1100px;
        margin: 30px auto;
    }

    .card {
        background: #ffffff;
        padding: 26px;
        margin-bottom: 24px;
        border-radius: 12px;
        border: 1px solid #e2e8e4;
        box-shadow: 0 4px 14px rgba(20, 83, 45, 0.06);
    }

    .card h2 {
        margin: 0 0 20px;
        color: #14532d;
        font-size: 21px;
    }

    label {
        display: block;
        margin: 16px 0 7px;
        font-weight: 600;
        font-size: 14px;
    }

    input,
    select {
        display: block;
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #cbd5ce;
        border-radius: 7px;
        background: #ffffff;
        color: #1f2937;
        font-size: 15px;
        outline: none;
    }

    input:focus,
    select:focus {
        border-color: #15803d;
        box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.12);
    }

    button {
        background: #166534;
        color: white;
        border: none;
        border-radius: 7px;
        padding: 12px 20px;
        margin-top: 20px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s ease;
    }

    button:hover {
        background: #14532d;
    }

    .success {
        padding: 12px 14px;
        color: #166534;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 7px;
    }

    .error {
        padding: 12px 14px;
        color: #b91c1c;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 7px;
    }

    .card p {
        font-size: 14px;
        color: #64748b;
    }

    .card a:not(.back) {
        color: #166534;
        font-weight: 600;
    }

    .table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        min-width: 500px;
    }

    th {
        background: #f0fdf4;
        color: #14532d;
        font-weight: 700;
    }

    th,
    td {
        text-align: left;
        padding: 14px 12px;
        border-bottom: 1px solid #e5e7eb;
    }

    tbody tr:hover {
        background: #f8faf9;
    }

    tbody tr:last-child td {
        border-bottom: none;
    }

    @media (max-width: 600px) {
        header {
            padding: 20px;
        }

        header h1 {
            font-size: 22px;
        }

        main {
            width: 94%;
            margin: 20px auto;
        }

        .card {
            padding: 18px;
        }
    }
</style>
</head>
<body>
<header>
    <h1>Course Management</h1>
    <a class="back" href="dashboard.php">Back to Dashboard</a>
</header>

<main>
    <section class="card">
        <h2>Add New Course</h2>

        <?php if ($message !== ""): ?>
            <p class="success"><?= h($message) ?></p>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <p class="error"><?= h($error) ?></p>
        <?php endif; ?>

        <?php if ($lecturers->num_rows > 0): ?>
            <form method="post">
                <input type="hidden" name="csrf_token"
                       value="<?= h($_SESSION["csrf_token"]) ?>">

                <label for="course_code">Course Code</label>
                <input id="course_code" name="course_code"
                       placeholder="e.g. CSC301" maxlength="20" required>

                <label for="course_title">Course Title</label>
                <input id="course_title" name="course_title"
                       placeholder="e.g. Web Development"
                       maxlength="150" required>

                <label for="lecturer_id">Assign Lecturer</label>
                <select id="lecturer_id" name="lecturer_id" required>
                    <option value="">-- Select Lecturer --</option>
                    <?php while ($lecturer = $lecturers->fetch_assoc()): ?>
                        <option value="<?= (int)$lecturer["id"] ?>">
                            <?= h($lecturer["full_name"]) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <button type="submit">Save Course</button>
            </form>
        <?php else: ?>
            <p>Create a lecturer account before adding a course.</p>
            <a href="lecturers.php">Go to Lecturer Management</a>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Registered Courses</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Title</th>
                        <th>Assigned Lecturer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($courses->num_rows > 0): ?>
                        <?php while ($course = $courses->fetch_assoc()): ?>
                            <tr>
                                <td><?= h($course["course_code"]) ?></td>
                                <td><?= h($course["course_title"]) ?></td>
                                <td><?= h($course["lecturer_name"] ?? "Not assigned") ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3">No courses registered yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
<?php $conn->close(); ?>
