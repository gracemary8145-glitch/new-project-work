
<?php
require_once "session_config.php";
require_once 'db_connect.php';

// Require an authenticated administrator or lecturer.
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

function validDate($date) {
    if (!is_string($date) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    $parts = explode('-', $date);
    return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
}

// Read report filters.
$courseId = filter_var(
    $_GET['course_id'] ?? '',
    FILTER_VALIDATE_INT
);

$today = date('Y-m-d');
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? $today;

$error = '';
$reportRows = [];
$selectedCourse = null;

// Get only courses the current user is allowed to view.
if ($role === 'admin') {
    $courseStmt = $conn->prepare(
        'SELECT id, course_code, course_title
         FROM courses
         ORDER BY course_code'
    );
} else {
    $courseStmt = $conn->prepare(
        'SELECT id, course_code, course_title
         FROM courses
         WHERE lecturer_id = ?
         ORDER BY course_code'
    );
    $courseStmt->bind_param('i', $userId);
}

$courseStmt->execute();
$courseResult = $courseStmt->get_result();

// Validate report filters.
if (
    !validDate($startDate) ||
    !validDate($endDate) ||
    $startDate > $endDate
) {
    $error = 'Please enter a valid date range. The start date must not be after the end date.';
} elseif ($courseId === false || $courseId === null || $courseId < 1) {
    $error = 'Please select a course to generate the report.';
} else {
    // Verify course access on the server.
    if ($role === 'admin') {
        $stmt = $conn->prepare(
            'SELECT id, course_code, course_title
             FROM courses WHERE id = ?'
        );
        $stmt->bind_param('i', $courseId);
    } else {
        $stmt = $conn->prepare(
            'SELECT id, course_code, course_title
             FROM courses
             WHERE id = ? AND lecturer_id = ?'
        );
        $stmt->bind_param('ii', $courseId, $userId);
    }

    $stmt->execute();
    $selectedCourse = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$selectedCourse) {
        $error = 'The selected course does not exist or you are not permitted to view its report.';
    } else {
        // Include every registered student, even if they have no
        // attendance records for this course and date range.
        $stmt = $conn->prepare(
            "SELECT
                s.id,
                s.matric_number,
                s.full_name,
                COUNT(a.id) AS total_classes,
                COALESCE(SUM(a.status = 'Present'), 0) AS present_count,
                COALESCE(SUM(a.status = 'Absent'), 0) AS absent_count,
                COALESCE(SUM(a.status = 'Late'), 0) AS late_count
             FROM students s
             LEFT JOIN attendance a
                ON a.student_id = s.id
               AND a.course_id = ?
               AND a.attendance_date BETWEEN ? AND ?
             GROUP BY s.id, s.matric_number, s.full_name
             ORDER BY s.full_name"
        );

        $stmt->bind_param('iss', $courseId, $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $row['total_classes'] = (int)$row['total_classes'];
            $row['present_count'] = (int)$row['present_count'];
            $row['absent_count'] = (int)$row['absent_count'];
            $row['late_count'] = (int)$row['late_count'];

            // Attendance percentage counts Present and Late
            // as attended. Change this policy if your school
            // treats Late differently.
            $attended = $row['present_count'] + $row['late_count'];

            $row['percentage'] = $row['total_classes'] > 0
                ? round(($attended / $row['total_classes']) * 100, 2)
                : null;

            $reportRows[] = $row;
        }

        $stmt->close();
    }
}

$courseStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports</title>

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
    body > .panel,
    body > .no-print {
        width: 92%;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
    }

    body > .no-print:first-child {
        margin-top: 20px;
    }

    body > h1 {
        margin-top: 25px;
        margin-bottom: 8px;
        color: #14532d;
        font-size: 28px;
    }

    a {
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

    label {
        display: block;
        margin: 12px 0 7px;
        font-size: 14px;
        font-weight: 600;
    }

    select,
    input[type="date"] {
        width: 100%;
        max-width: 100%;
        padding: 12px;
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

    .filters {
        display: flex;
        gap: 18px;
        flex-wrap: wrap;
    }

    .filters > div {
        flex: 1 1 190px;
        min-width: 0;
    }

    button {
        padding: 12px 18px;
        background: #166534;
        color: #ffffff;
        border: none;
        border-radius: 7px;
        font-family: inherit;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s ease;
    }

    button:hover {
        background: #14532d;
    }

    .error {
        padding: 12px 14px;
        color: #b91c1c;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 7px;
    }

    .muted {
        color: #64748b;
        font-size: 14px;
    }

    .low {
        color: #b91c1c;
        font-weight: 700;
    }

    .table-wrap {
        width: 100%;
        overflow-x: auto;
        margin-top: 18px;
    }

    table {
        width: 100%;
        min-width: 850px;
        border-collapse: collapse;
    }

    th {
        background: #f0fdf4;
        color: #14532d;
        font-weight: 700;
        white-space: nowrap;
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

    @media (max-width: 650px) {
        body > h1,
        body > p,
        body > .panel,
        body > .no-print {
            width: 94%;
        }

        body > h1 {
            font-size: 23px;
        }

        .panel {
            padding: 17px;
        }

        .filters {
            gap: 8px;
        }

        .filters > div {
            flex-basis: 100%;
        }

        th,
        td {
            padding: 9px;
        }
    }

    @media print {
        body {
            background: #ffffff;
            color: #000000;
        }

        .no-print {
            display: none !important;
        }

        body > h1,
        body > p,
        body > .panel {
            width: 100%;
            max-width: none;
            margin: 10px 0;
        }

        .panel {
            border: none;
            box-shadow: none;
            padding: 0;
        }

        table {
            min-width: 0;
        }

        th,
        td {
            padding: 6px;
            font-size: 10px;
            border: 1px solid #cccccc;
        }

        th {
            background: #eeeeee !important;
            color: #000000;
        }
    }
</style>
</head>

<body>

<div class="no-print">
    <p>
        <a href="dashboard.php">Dashboard</a> |
        <a href="attendance.php">Record Attendance</a> |
        <a href="logout.php">Logout</a>
    </p>
</div>

<h1>Student Attendance Report</h1>

<p>
    Signed in as
    <strong><?= e($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') ?></strong>
    (<?= e($role) ?>)
</p>

<div class="panel no-print">
    <h2>Generate Report</h2>

    <?php if ($error): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="GET" action="attendance_reports.php">
        <div class="filters">
            <div>
                <label for="course_id">Course</label>
                <select name="course_id" id="course_id" required>
                    <option value="">-- Select course --</option>
                    <?php while ($course = $courseResult->fetch_assoc()): ?>
                        <option
                            value="<?= (int)$course['id'] ?>"
                            <?= (string)$courseId === (string)$course['id'] ? 'selected' : '' ?>
                        >
                            <?= e($course['course_code']) ?> -
                            <?= e($course['course_title']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label for="start_date">Start date</label>
                <input
                    type="date"
                    id="start_date"
                    name="start_date"
                    value="<?= e(validDate($startDate) ? $startDate : date('Y-m-01')) ?>"
                    required
                >
            </div>

            <div>
                <label for="end_date">End date</label>
                <input
                    type="date"
                    id="end_date"
                    name="end_date"
                    value="<?= e(validDate($endDate) ? $endDate : $today) ?>"
                    required
                >
            </div>
        </div>

        <p><button type="submit">Generate Report</button></p>
    </form>
</div>

<?php if ($selectedCourse && !$error): ?>

    <div class="panel">
        <h2>
            <?= e($selectedCourse['course_code']) ?> -
            <?= e($selectedCourse['course_title']) ?>
        </h2>

        <p>
            <strong>Reporting period:</strong>
            <?= e($startDate) ?> to <?= e($endDate) ?>
        </p>

        <p class="muted">
            Attendance percentage = (Present + Late) / recorded attendance entries × 100.
            Students with no records have no calculated percentage.
        </p>

        
         <div class="no-print">
             <button type="button" onclick="window.print()">Print Report</button>

                 <a
                 href="export_attendance.php?course_id=<?= (int)$courseId ?>&amp;start_date=<?= e($startDate) ?>&amp;end_date=<?= e($endDate) ?>"
                 style="display:inline-block; padding:10px 14px;
                 background:#166534; color:white;
                 text-decoration:none; border-radius:4px;
                 margin-left:8px;"
                 >
                 Export to CSV / Excel
                 </a>
         </div>


        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>S/N</th>
                        <th>Matric Number</th>
                        <th>Student Name</th>
                        <th>Recorded Classes</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Late</th>
                        <th>Attendance %</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!$reportRows): ?>
                        <tr>
                            <td colspan="8">No students have been registered.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reportRows as $index => $row): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= e($row['matric_number']) ?></td>
                                <td><?= e($row['full_name']) ?></td>
                                <td><?= $row['total_classes'] ?></td>
                                <td><?= $row['present_count'] ?></td>
                                <td><?= $row['absent_count'] ?></td>
                                <td><?= $row['late_count'] ?></td>
                                <td>
                                    <?php if ($row['percentage'] === null): ?>
                                        N/A
                                    <?php else: ?>
                                        <span class="<?= $row['percentage'] < 75 ? 'low' : '' ?>">
                                            <?= number_format($row['percentage'], 2) ?>%
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p class="muted">
            Note: This report counts Late as attended. The 75% indicator is a sample
            threshold and should be adjusted to your institution's policy.
        </p>
    </div>

<?php endif; ?>

</body>
</html>
