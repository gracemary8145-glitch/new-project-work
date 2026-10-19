
<?php
require_once "session_config.php";
require_once 'db_connect.php';

// Only authenticated administrators and lecturers can export reports.
if (
    !isset($_SESSION['user_id'], $_SESSION['role']) ||
    !in_array($_SESSION['role'], ['admin', 'lecturer'], true)
) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];

// Validate dates.
function validExportDate($date) {
    if (
        !is_string($date) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
    ) {
        return false;
    }

    $parts = explode('-', $date);

    return checkdate(
        (int)$parts[1],
        (int)$parts[2],
        (int)$parts[0]
    );
}

// Protect spreadsheet users from CSV formula injection.
function safeCsvValue($value) {
    $value = (string)$value;

    if (preg_match('/^[\s]*[=+\-@\t\r]/u', $value)) {
        return "'" . $value;
    }

    return $value;
}

$courseId = filter_var(
    $_GET['course_id'] ?? '',
    FILTER_VALIDATE_INT
);

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

if (
    $courseId === false ||
    $courseId === null ||
    $courseId < 1 ||
    !validExportDate($startDate) ||
    !validExportDate($endDate) ||
    $startDate > $endDate
) {
    http_response_code(400);
    exit('Invalid course or date range. Return to Attendance Reports and try again.');
}

// Verify that this user can access the selected course.
if ($role === 'admin') {
    $stmt = $conn->prepare(
        'SELECT course_code, course_title
         FROM courses
         WHERE id = ?'
    );
    $stmt->bind_param('i', $courseId);
} else {
    $stmt = $conn->prepare(
        'SELECT course_code, course_title
         FROM courses
         WHERE id = ? AND lecturer_id = ?'
    );
    $stmt->bind_param('ii', $courseId, $userId);
}

$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course) {
    http_response_code(403);
    exit('You do not have permission to export this course report.');
}

// Get attendance totals using the same rules as the reports page.
$stmt = $conn->prepare(
    "SELECT
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

// Send a CSV download with a UTF-8 BOM for better Excel compatibility.
$filename = 'attendance_' .
    preg_replace('/[^A-Za-z0-9_-]/', '_', $course['course_code']) .
    '_' . $startDate . '_to_' . $endDate . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');

$output = fopen('php://output', 'w');

// UTF-8 BOM helps Excel recognize the character encoding.
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, ['Student Attendance Report']);
fputcsv($output, ['Course Code', safeCsvValue($course['course_code'])]);
fputcsv($output, ['Course Title', safeCsvValue($course['course_title'])]);
fputcsv($output, ['Start Date', $startDate]);
fputcsv($output, ['End Date', $endDate]);
fputcsv($output, []);

// Column headings.
fputcsv($output, [
    'S/N',
    'Matric Number',
    'Student Name',
    'Recorded Classes',
    'Present',
    'Absent',
    'Late',
    'Attendance Percentage'
]);

$serial = 1;

while ($row = $result->fetch_assoc()) {
    $total = (int)$row['total_classes'];
    $present = (int)$row['present_count'];
    $absent = (int)$row['absent_count'];
    $late = (int)$row['late_count'];

    // Present and Late both count as attended.
    $attended = $present + $late;

    $percentage = $total > 0
        ? number_format(($attended / $total) * 100, 2) . '%'
        : 'N/A';

    fputcsv($output, [
        $serial++,
        safeCsvValue($row['matric_number']),
        safeCsvValue($row['full_name']),
        $total,
        $present,
        $absent,
        $late,
        $percentage
    ]);
}

fclose($output);
$stmt->close();
exit;
