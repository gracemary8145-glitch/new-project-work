
<?php
require_once "session_config.php";
require_once 'db_connect.php';

// Only logged-in administrators and lecturers may access this page.
if (
    !isset($_SESSION['user_id'], $_SESSION['role']) ||
    !in_array($_SESSION['role'], ['admin', 'lecturer'], true)
) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Create a CSRF token for this page.
if (empty($_SESSION['attendance_csrf'])) {
    $_SESSION['attendance_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['attendance_csrf'];

$message = '';
$error = '';

// Use today's date by default.
$today = date('Y-m-d');
$selectedDate = $_GET['date'] ?? $today;
$selectedCourseId = (int)($_GET['course_id'] ?? 0);

// Validate the date format and actual calendar date.
function validDate($date) {
    if (!is_string($date) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    $parts = explode('-', $date);
    return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
}

// Return a course only if the current user is allowed to access it.
function getAllowedCourse($conn, $courseId, $userId, $role) {
    if ($role === 'admin') {
        $stmt = $conn->prepare(
            'SELECT id, course_code, course_title, lecturer_id
             FROM courses WHERE id = ?'
        );
        $stmt->bind_param('i', $courseId);
    } else {
        $stmt = $conn->prepare(
            'SELECT id, course_code, course_title, lecturer_id
             FROM courses
             WHERE id = ? AND lecturer_id = ?'
        );
        $stmt->bind_param('ii', $courseId, $userId);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $course = $result->fetch_assoc();
    $stmt->close();

    return $course ?: null;
}

// Process attendance submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($postedToken) ||
        !hash_equals($csrfToken, $postedToken)) {
        $error = 'Invalid form token. Refresh the page and try again.';
    } else {
        $selectedCourseId = filter_var(
            $_POST['course_id'] ?? null,
            FILTER_VALIDATE_INT
        );
        $selectedDate = $_POST['attendance_date'] ?? '';
        $submittedAttendance = $_POST['attendance'] ?? null;

        if (!$selectedCourseId || $selectedCourseId < 1) {
            $error = 'Please select a valid course.';
        } elseif (!validDate($selectedDate)) {
            $error = 'Please select a valid attendance date.';
        } elseif (!is_array($submittedAttendance) ||
                  !$submittedAttendance) {
            $error = 'No student attendance was submitted.';
        } else {
            // IMPORTANT: check permissions again when saving.
            $course = getAllowedCourse(
                $conn,
                $selectedCourseId,
                $userId,
                $role
            );

            if (!$course) {
                $error = 'You are not allowed to record attendance for this course.';
            } else {
                // Get the actual student IDs in the database.
                $studentResult = $conn->query(
                    'SELECT id FROM students ORDER BY id'
                );

                $validStudentIds = [];
                while ($student = $studentResult->fetch_assoc()) {
                    $validStudentIds[] = (int)$student['id'];
                }

                // Require one valid attendance status for every student.
                $cleanAttendance = [];
                $invalidSubmission = false;

                foreach ($validStudentIds as $studentId) {
                    $key = (string)$studentId;

                    if (!array_key_exists($key, $submittedAttendance) ||
                        !is_string($submittedAttendance[$key]) ||
                        !in_array(
                            $submittedAttendance[$key],
                            ['Present', 'Absent', 'Late'],
                            true
                        )) {
                        $invalidSubmission = true;
                        break;
                    }

                    $cleanAttendance[$studentId] =
                        $submittedAttendance[$key];
                }

                // Reject unknown student IDs or incomplete submissions.
                if (
                    $invalidSubmission ||
                    count($submittedAttendance) !== count($validStudentIds)
                ) {
                    $error = 'Attendance data is incomplete or invalid. Refresh and try again.';
                } else {
                    try {
                        $conn->begin_transaction();

                        $stmt = $conn->prepare(
                            'INSERT INTO attendance
                                (student_id, course_id, attendance_date,
                                 status, recorded_by)
                             VALUES (?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE
                                status = VALUES(status),
                                recorded_by = VALUES(recorded_by)'
                        );

                        foreach ($cleanAttendance as $studentId => $status) {
                            $stmt->bind_param(
                                'iissi',
                                $studentId,
                                $selectedCourseId,
                                $selectedDate,
                                $status,
                                $userId
                            );
                            $stmt->execute();
                        }

                        $stmt->close();
                        $conn->commit();

                        // Redirect after saving to prevent accidental resubmission.
                        header(
                            'Location: attendance.php?course_id=' .
                            $selectedCourseId .
                            '&date=' . urlencode($selectedDate) .
                            '&msg=saved'
                        );
                        exit;
                    } catch (Throwable $ex) {
                        $conn->rollback();
                        error_log('Attendance save failed: ' . $ex->getMessage());
                        $error = 'Attendance could not be saved. Check your database setup and try again.';
                    }
                }
            }
        }
    }
}

// Show a success message after saving.
if (($_GET['msg'] ?? '') === 'saved') {
    $message = 'Attendance saved successfully.';
}

// Get only the courses this user is permitted to access.
if ($role === 'admin') {
    $courseResult = $conn->query(
        'SELECT c.id, c.course_code, c.course_title, u.full_name
         FROM courses c
         LEFT JOIN users u ON c.lecturer_id = u.id
         ORDER BY c.course_code'
    );
} else {
    $stmt = $conn->prepare(
        'SELECT c.id, c.course_code, c.course_title, u.full_name
         FROM courses c
         LEFT JOIN users u ON c.lecturer_id = u.id
         WHERE c.lecturer_id = ?
         ORDER BY c.course_code'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $courseResult = $stmt->get_result();
}

// Load the selected course only if the user is allowed to access it.
$selectedCourse = null;
if ($selectedCourseId > 0) {
    $selectedCourse = getAllowedCourse(
        $conn,
        $selectedCourseId,
        $userId,
        $role
    );

    if (!$selectedCourse && !$error) {
        $error = 'That course does not exist or you do not have permission to access it.';
        $selectedCourseId = 0;
    }
}

// Load students and existing attendance for the selected course and date.
$students = [];
if ($selectedCourse && validDate($selectedDate)) {
    $stmt = $conn->prepare(
        'SELECT s.id, s.matric_number, s.full_name, a.status
         FROM students s
         LEFT JOIN attendance a
           ON a.student_id = s.id
          AND a.course_id = ?
          AND a.attendance_date = ?
         ORDER BY s.full_name'
    );
    $stmt->bind_param('is', $selectedCourseId, $selectedDate);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($student = $result->fetch_assoc()) {
        $students[] = $student;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Attendance</title>
    
<style>
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding: 0;
        font-family: Arial, sans-serif;
        background: #f3f7f4;
        color: #1f2937;
        line-height: 1.6;
    }

    body > h1,
    body > p,
    body > .panel {
        width: 92%;
        max-width: 1100px;
        margin-left: auto;
        margin-right: auto;
    }

    body > h1 {
        margin-top: 30px;
        margin-bottom: 8px;
        color: #14532d;
        font-size: 28px;
    }

    body > p {
        margin-top: 10px;
        margin-bottom: 12px;
    }

    a {
        display: inline-block;
        margin-right: 12px;
        color: #166534;
        font-weight: 600;
        text-decoration: none;
    }

    a:hover {
        text-decoration: underline;
    }

    .panel {
        background: #ffffff;
        border: 1px solid #e2e8e4;
        border-radius: 12px;
        padding: 24px;
        margin-top: 22px;
        margin-bottom: 24px;
        box-shadow: 0 4px 14px rgba(20, 83, 45, 0.06);
    }

    .panel h2 {
        margin-top: 0;
        margin-bottom: 18px;
        color: #14532d;
        font-size: 21px;
    }

    .panel p {
        margin: 10px 0;
    }

    label {
        display: block;
        margin: 16px 0 7px;
        font-weight: 600;
        font-size: 14px;
    }

    select,
    input[type="date"] {
        display: block;
        width: 100%;
        max-width: 100%;
        padding: 12px 14px;
        font-family: inherit;
        font-size: 15px;
        color: #1f2937;
        background: #ffffff;
        border: 1px solid #cbd5ce;
        border-radius: 7px;
    }

    select:focus,
    input[type="date"]:focus {
        outline: none;
        border-color: #15803d;
        box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.12);
    }

    .actions {
        margin-top: 20px;
    }

    button {
        padding: 12px 20px;
        background: #166534;
        color: #ffffff;
        border: none;
        border-radius: 7px;
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

    .table-wrap {
        width: 100%;
        overflow-x: auto;
        margin-top: 18px;
    }

    table {
        width: 100%;
        min-width: 650px;
        border-collapse: collapse;
    }

    th {
        background: #f0fdf4;
        color: #14532d;
        font-weight: 700;
    }

    th,
    td {
        padding: 13px 12px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }

    tbody tr:hover {
        background: #f8faf9;
    }

    tbody tr:last-child td {
        border-bottom: none;
    }

    td select {
        min-width: 130px;
    }

    @media (max-width: 650px) {
        body > h1 {
            font-size: 23px;
            margin-top: 22px;
        }

        body > h1,
        body > p,
        body > .panel {
            width: 94%;
        }

        .panel {
            padding: 17px;
        }

        .panel h2 {
            font-size: 19px;
        }

        th,
        td {
            padding: 9px;
        }
    }
</style>
</head>
<body>

<h1>Record Student Attendance</h1>
<p>
    Logged in as <strong><?= e($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') ?></strong>
    (<?= e($role) ?>)
</p>

<p>
    <a href="dashboard.php">Dashboard</a>
    <a href="logout.php">Logout</a>
</p>

<?php if ($message): ?>
    <p class="success"><?= e($message) ?></p>
<?php endif; ?>

<?php if ($error): ?>
    <p class="error"><?= e($error) ?></p>
<?php endif; ?>

<div class="panel">
    <h2>1. Choose Course and Date</h2>

    <?php if ($courseResult->num_rows === 0): ?>
        <p>No courses are assigned to your account yet.</p>
    <?php else: ?>
        <form method="GET" action="attendance.php">
            <label for="course_id">Course</label>
            <select name="course_id" id="course_id" required>
                <option value="">-- Select a course --</option>
                <?php while ($course = $courseResult->fetch_assoc()): ?>
                    <option
                        value="<?= (int)$course['id'] ?>"
                        <?= $selectedCourseId === (int)$course['id'] ? 'selected' : '' ?>
                    >
                        <?= e($course['course_code']) ?> -
                        <?= e($course['course_title']) ?>
                        <?php if ($role === 'admin' && !empty($course['full_name'])): ?>
                            (<?= e($course['full_name']) ?>)
                        <?php endif; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="date">Attendance date</label>
            <input
                type="date"
                id="date"
                name="date"
                value="<?= e(validDate($selectedDate) ? $selectedDate : $today) ?>"
                required
            >

            <div class="actions">
                <button type="submit">Load Students</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php if ($selectedCourse && validDate($selectedDate)): ?>
    <div class="panel">
        <h2>2. Mark Attendance</h2>
        <p>
            <strong>Course:</strong>
            <?= e($selectedCourse['course_code']) ?> -
            <?= e($selectedCourse['course_title']) ?>
        </p>
        <p><strong>Date:</strong> <?= e($selectedDate) ?></p>

        <?php if (count($students) === 0): ?>
            <p>No students have been registered yet. Add students before recording attendance.</p>
        <?php else: ?>
            <form method="POST" action="attendance.php">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="course_id" value="<?= (int)$selectedCourseId ?>">
                <input type="hidden" name="attendance_date" value="<?= e($selectedDate) ?>">

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>S/N</th>
                                <th>Matric Number</th>
                                <th>Student Name</th>
                                <th>Attendance Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $index => $student): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= e($student['matric_number']) ?></td>
                                    <td><?= e($student['full_name']) ?></td>
                                    <td>
                                        <select
                                            name="attendance[<?= (int)$student['id'] ?>]"
                                            required
                                        >
                                            <option value="">-- Select status --</option>
                                            <?php foreach (['Present', 'Absent', 'Late'] as $status): ?>
                                                <option
                                                    value="<?= e($status) ?>"
                                                    <?= $student['status'] === $status ? 'selected' : '' ?>
                                                >
                                                    <?= e($status) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="actions">
                    <button type="submit">Save Attendance</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

</body>
</html>
