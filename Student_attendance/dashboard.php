
<?php
require_once "session_config.php";
require_once 'db_connect.php';

// Require a valid login.
if (
    !isset($_SESSION['user_id'], $_SESSION['role']) ||
    !in_array($_SESSION['role'], ['admin', 'lecturer'], true)
) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];
$fullName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$stats = [];

if ($role === 'admin') {
    // Administrator statistics.
    $result = $conn->query('SELECT COUNT(*) AS total FROM students');
    $stats['students'] = (int)$result->fetch_assoc()['total'];

    $result = $conn->query(
        "SELECT COUNT(*) AS total FROM users WHERE role = 'lecturer'"
    );
    $stats['lecturers'] = (int)$result->fetch_assoc()['total'];

    $result = $conn->query('SELECT COUNT(*) AS total FROM courses');
    $stats['courses'] = (int)$result->fetch_assoc()['total'];

    $result = $conn->query('SELECT COUNT(*) AS total FROM attendance');
    $stats['attendance'] = (int)$result->fetch_assoc()['total'];

} else {
    // Lecturer statistics are restricted to their assigned courses.
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS total
         FROM courses
         WHERE lecturer_id = ?'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stats['courses'] = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS total
         FROM attendance a
         INNER JOIN courses c ON c.id = a.course_id
         WHERE c.lecturer_id = ?'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stats['attendance'] = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $role === 'admin' ? 'Administrator' : 'Lecturer' ?> Dashboard</title>
    <link rel="stylesheet" href="style.css">

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #1f2937;
        }

        .topbar {
            background: #173b2b;
            color: white;
            padding: 20px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .topbar h1 {
            margin: 0;
            font-size: 23px;
        }

        .topbar p {
            margin: 6px 0 0;
            color: #e0eee5;
        }

        .topbar a {
            color: white;
            text-decoration: none;
            border: 1px solid #c8dfd0;
            padding: 9px 14px;
            border-radius: 6px;
        }

        main {
            width: 90%;
            max-width: 1150px;
            margin: 30px auto;
        }

        .welcome {
            margin-bottom: 24px;
        }

        .welcome h2 {
            margin-bottom: 8px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }

        
.stat-card,
.section {
    background: #ffffff;
    padding: 22px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card {
    border-top: 4px solid #198754;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}

.stat-card p {
    margin: 0 0 12px;
    color: #64748b;
    font-size: 14px;
    font-weight: 500;
}

.stat-card strong {
    display: block;
    margin-top: 12px;
    font-size: 32px;
    font-weight: 700;
    line-height: 1.2;
    color: #173b2b;
}

 
 
        .section h2 {
            margin-top: 0;
            font-size: 20px;
        }

        
.links {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    align-items: stretch;
}
   min-width: 0;
min-height: 105px;
box-sizing: border-box;
display: flex;
flex-direction: column;
justify-content: flex-start;


@media (max-width: 900px) {
    .stats,
    .links {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 560px) {
    .stats,
    .links {
        grid-template-columns: minmax(0, 1fr);
    }

    .topbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
}


@media (max-width: 560px) {
    .links {
        grid-template-columns: 1fr;
    }
}

       
.links a {
    display: flex;
    flex-direction: column;
    height: 100%;
    text-decoration: none;
    background: #f8fafc;
    color: #173b2b;
    padding: 17px;
    border: 1px solid #dbe4de;
    border-radius: 8px;
    font-weight: bold;
    transition: background 0.2s ease, box-shadow 0.2s ease,
                transform 0.2s ease;
}

    

.links a {
    transition: background 0.2s ease,
                transform 0.2s ease,
                box-shadow 0.2s ease;
}

.links a:hover {
    background: #e8f2eb;
    transform: translateY(-3px);
    box-shadow: 0 5px 12px rgba(23, 59, 43, 0.10);
}

   

        
        .links small {
            display: block;
            margin-top: 7px;
            color: #64748b;
            font-weight: normal;
        }

        footer {
            text-align: center;
            color: #64748b;
            padding: 25px;
        }
        
          /* Dashboard visual refinements */

          .topbar h1,
.topbar h2,
.topbar h3 {
    color: #ffffff;
}

.topbar p {
    color: #e2e8f0;
}

.card,
.stat-card {
    transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.card:hover,
.stat-card:hover {
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.10);
    transform: translateY(-2px);
}

a:focus-visible,
button:focus-visible {
    outline: 3px solid #93c5fd;
    outline-offset: 3px;
}
 
    </style>
</head>
<body>

<header class="topbar">
    <div>
        <h1>Student Attendance Management System</h1>
        <p><?= $role === 'admin' ? 'Administrator Portal' : 'Lecturer Portal' ?></p>
    </div>

    <a href="logout.php">Log Out</a>
</header>

<main>
    <section class="welcome">
        <h2>Welcome, <?= e($fullName) ?>!</h2>
        <p>
            You are logged in as
            <strong><?= e(ucfirst($role)) ?></strong>.
            Use the options below to access your available functions.
        </p>
    </section>

    <section class="stats">
        <?php if ($role === 'admin'): ?>
            <div class="stat-card">
                <p>Registered Students</p>
                <strong><?= $stats['students'] ?></strong>
            </div>

            <div class="stat-card">
                <p>Lecturers</p>
                <strong><?= $stats['lecturers'] ?></strong>
            </div>
        <?php endif; ?>

        <div class="stat-card">
            <p><?= $role === 'admin' ? 'Total Courses' : 'My Assigned Courses' ?></p>
            <strong><?= $stats['courses'] ?></strong>
        </div>

        <div class="stat-card">
            <p><?= $role === 'admin' ? 'Attendance Records' : 'Records in My Courses' ?></p>
            <strong><?= $stats['attendance'] ?></strong>
        </div>
    </section>

    <?php if ($role === 'admin'): ?>
        <section class="section">
            <h2>Administration</h2>

            <div class="links">
                <a href="students.php">
                    Manage Students
                    <small>Register, search, edit and manage student records.</small>
                </a>

                <a href="lecturers.php">
                    Manage Lecturers
                    <small>Create and manage lecturer accounts.</small>
                </a>

                <a href="courses.php">
                    Manage Courses
                    <small>Create courses and assign lecturers.</small>
                </a>

                <a href="attendance.php">
                    Record Attendance
                    <small>Open the attendance recording page.</small>
                </a>

                <a href="attendance_reports.php">
                    Attendance Reports
                    <small>Review attendance totals and percentages.</small>
                </a>

                <a href="attendance_reports.php">
                    Export Attendance
                    <small>Generate a report and export it to CSV.</small>
                </a>
            </div>
        </section>

    <?php else: ?>
        <section class="section">
            <h2>Lecturer Functions</h2>

            <div class="links">
                <a href="attendance.php">
                    Record Attendance
                    <small>Mark student attendance for your assigned courses.</small>
                </a>

                <a href="attendance_reports.php">
                    View Attendance Reports
                    <small>Review reports for courses assigned to you.</small>
                </a>

                <a href="attendance_reports.php">
                    Export Attendance
                    <small>Generate a report and download it as CSV.</small>
                </a>
            </div>
        </section>
    <?php endif; ?>
</main>

<footer>
    Student Attendance Management System
</footer>

</body>
</html>
