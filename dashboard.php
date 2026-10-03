```php
<?php
session_start();
require_once "config/database.php";

/*
|--------------------------------------------------------------------------
| PROTECT PAGE
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| DASHBOARD DATA
|--------------------------------------------------------------------------
*/

// Current month
$currentMonth = date('Y-m');

// Display month
$monthName = date('F Y');

/*
|--------------------------------------------------------------------------
| 1. ACTIVE MEMBERS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM members
    WHERE status = 'Active'
");

$stmt->execute();
$activeMembers = (int) $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| 2. PRESENT THIS MONTH
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM attendance
    WHERE status = 'Present'
    AND DATE_FORMAT(attendance_date, '%Y-%m') = ?
");

$stmt->execute([$currentMonth]);
$presentThisMonth = (int) $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| 3. ABSENT THIS MONTH
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM attendance
    WHERE status = 'Absent'
    AND DATE_FORMAT(attendance_date, '%Y-%m') = ?
");

$stmt->execute([$currentMonth]);
$absentThisMonth = (int) $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| 4. TOTAL ATTENDANCE RECORDS THIS MONTH
|--------------------------------------------------------------------------
*/

$totalAttendance = $presentThisMonth + $absentThisMonth;

/*
|--------------------------------------------------------------------------
| 5. ATTENDANCE RATE
|--------------------------------------------------------------------------
*/

if ($totalAttendance > 0) {
    $attendanceRate =
        ($presentThisMonth / $totalAttendance) * 100;
} else {
    $attendanceRate = 0;
}

/*
|--------------------------------------------------------------------------
| 6. SUNDAYS RECORDED
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT attendance_date)
    FROM attendance
    WHERE DATE_FORMAT(attendance_date, '%Y-%m') = ?
");

$stmt->execute([$currentMonth]);
$sundaysRecorded = (int) $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| 7. LATEST ATTENDANCE DATE
|--------------------------------------------------------------------------
*/

$stmt = $conn->query("
    SELECT MAX(attendance_date)
    FROM attendance
");

$latestAttendanceDate = $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| 8. LATEST ATTENDANCE SUMMARY
|--------------------------------------------------------------------------
*/

$latestPresent = 0;
$latestAbsent = 0;
$latestRate = 0;

if ($latestAttendanceDate) {

    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM attendance
        WHERE attendance_date = ?
        AND status = 'Present'
    ");

    $stmt->execute([$latestAttendanceDate]);
    $latestPresent = (int) $stmt->fetchColumn();

    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM attendance
        WHERE attendance_date = ?
        AND status = 'Absent'
    ");

    $stmt->execute([$latestAttendanceDate]);
    $latestAbsent = (int) $stmt->fetchColumn();

    $latestTotal = $latestPresent + $latestAbsent;

    if ($latestTotal > 0) {
        $latestRate =
            ($latestPresent / $latestTotal) * 100;
    }
}

/*
|--------------------------------------------------------------------------
| 9. RECENT ATTENDANCE
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        a.attendance_date,
        CONCAT(
            m.first_name,
            ' ',
            CASE
                WHEN m.middle_name IS NOT NULL
                AND m.middle_name != ''
                THEN CONCAT(m.middle_name, ' ')
                ELSE ''
            END,
            m.last_name
        ) AS member_name,
        m.member_code,
        m.ministry,
        a.status
    FROM attendance a
    INNER JOIN members m
        ON a.member_id = m.id
    ORDER BY a.attendance_date DESC, a.id DESC
    LIMIT 10
");

$stmt->execute();
$recentAttendance =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Dashboard | Church Attendance</title>

<style>

/* =========================
   RESET
========================= */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}


/* =========================
   BODY
========================= */

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f6f8;
    color: #17213b;
}


/* =========================
   SIDEBAR
========================= */

.sidebar {
    position: fixed;
    left: 0;
    top: 0;

    width: 240px;
    height: 100vh;

    background: #11182d;
    color: white;

    padding: 25px 15px;

    box-shadow: 3px 0 12px rgba(0,0,0,0.12);
}


/* SIDEBAR BRAND */

.sidebar-brand {
    text-align: center;
    padding: 10px 5px 30px;
    border-bottom: 1px solid rgba(255,255,255,0.15);
    margin-bottom: 20px;
}

.sidebar-brand .icon {
    font-size: 35px;
    margin-bottom: 8px;
}

.sidebar-brand h2 {
    font-size: 17px;
    line-height: 1.4;
}

.sidebar-brand p {
    font-size: 11px;
    color: #bfc9df;
    margin-top: 5px;
}


/* SIDEBAR LINKS */

.sidebar-menu {
    list-style: none;
}

.sidebar-menu li {
    margin-bottom: 8px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;

    gap: 12px;

    text-decoration: none;
    color: #d9e1f2;

    padding: 13px 15px;

    border-radius: 8px;

    font-size: 14px;

    transition: 0.2s;
}

.sidebar-menu a:hover {
    background: #1d2a4a;
    color: white;
}


/* ACTIVE SIDEBAR LINK */

.sidebar-menu a.active {
    background: #0877c9;
    color: white;
    font-weight: bold;
}


/* SIDEBAR ICON */

.menu-icon {
    width: 25px;
    text-align: center;
    font-size: 17px;
}


/* =========================
   MAIN CONTENT
========================= */

.main-content {
    margin-left: 240px;
    padding: 35px;
}


/* =========================
   CONTAINER
========================= */

.container {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
}


/* =========================
   HEADER
========================= */

.header {
    background:
        linear-gradient(
            135deg,
            #11182d,
            #062d66
        );

    color: white;

    padding: 30px;

    border-radius: 14px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 15px
        rgba(0,0,0,0.12);
}

.header h1 {
    font-size: 28px;
    margin-bottom: 8px;
}

.header p {
    font-size: 15px;
    color: #d9e4ff;
}


/* =========================
   STAT CARDS
========================= */

.stats {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 35px;
}

.stat-card {
    background: white;

    padding: 24px;

    border-radius: 12px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,0.08);

    border: 1px solid #eeeeee;
}

.stat-title {
    font-size: 13px;
    color: #666;
    margin-bottom: 10px;
}

.stat-number {
    font-size: 28px;
    font-weight: bold;
    color: #11182d;
}


/* =========================
   LATEST SUMMARY
========================= */

.latest-summary {
    background: white;

    padding: 25px;

    border-radius: 12px;

    margin-bottom: 35px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,0.08);
}

.latest-grid {
    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;

    margin-top: 15px;
}

.latest-card {
    background: #f7f9fc;

    padding: 20px;

    border-radius: 10px;
}

.latest-card h3 {
    font-size: 13px;
    color: #666;
    margin-bottom: 8px;
}

.latest-card p {
    font-size: 25px;
    font-weight: bold;
}


/* =========================
   SECTION TITLE
========================= */

.section-title {
    font-size: 22px;
    margin-bottom: 15px;
    color: #17213b;
}


/* =========================
   QUICK ACTIONS
========================= */

.quick-actions {
    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;

    margin-bottom: 40px;
}

.action-card {
    background: white;

    text-decoration: none;

    color: #17213b;

    padding: 22px;

    border-radius: 12px;

    border: 1px solid #e4e4e4;

    transition: 0.2s;

    display: block;
}

.action-card:hover {
    transform: translateY(-3px);

    box-shadow:
        0 6px 15px
        rgba(0,0,0,0.10);
}

.action-title {
    font-size: 16px;
    font-weight: bold;
    margin-bottom: 7px;
}

.action-description {
    font-size: 13px;
    color: #777;
}


/* =========================
   RECENT ATTENDANCE
========================= */

.recent-section {
    background: white;

    border-radius: 12px;

    padding: 25px;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,0.08);
}

.table-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #0877c9;

    color: white;

    padding: 13px;

    text-align: left;

    font-size: 14px;
}

td {
    padding: 13px;

    border-bottom:
        1px solid #eeeeee;

    font-size: 14px;
}

tr:hover {
    background: #f8fbff;
}


/* =========================
   STATUS
========================= */

.present {
    color: #14804a;
    font-weight: bold;
}

.absent {
    color: #c62828;
    font-weight: bold;
}


/* =========================
   NO RECORDS
========================= */

.no-records {
    text-align: center;

    color: #777;

    padding: 25px;
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 900px) {

    .sidebar {
        width: 200px;
    }

    .main-content {
        margin-left: 200px;
        padding: 25px;
    }

    .stats {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .latest-grid {
        grid-template-columns: 1fr;
    }

    .quick-actions {
        grid-template-columns: 1fr;
    }
}


@media (max-width: 600px) {

    .sidebar {
        position: relative;

        width: 100%;
        height: auto;

        padding: 15px;
    }

    .sidebar-brand {
        padding-bottom: 15px;
        margin-bottom: 10px;
    }

    .sidebar-menu {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 5px;
    }

    .sidebar-menu li {
        margin-bottom: 0;
    }

    .sidebar-menu a {
        justify-content: center;
        padding: 10px 5px;
        font-size: 12px;
    }

    .main-content {
        margin-left: 0;
        padding: 20px;
    }

    .header {
        padding: 22px;
    }

    .header h1 {
        font-size: 23px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .stat-number {
        font-size: 25px;
    }

    .recent-section {
        padding: 15px;
    }
}

</style>

</head>


<body>


<!-- =========================
     SIDEBAR
========================= -->

<aside class="sidebar">

    <div class="sidebar-brand">

        <div class="icon">
            ⛪
        </div>

        <h2>
            Church Attendance
        </h2>

        <p>
            Monitoring System
        </p>

    </div>


    <ul class="sidebar-menu">

        <!-- DASHBOARD -->

        <li>
            <a href="dashboard.php" class="active">

                <span class="menu-icon">
                    🏠
                </span>

                <span>
                    Dashboard
                </span>

            </a>
        </li>


        <!-- MEMBERS -->

        <li>
            <a href="members.php">

                <span class="menu-icon">
                    👥
                </span>

                <span>
                    Members
                </span>

            </a>
        </li>


        <!-- ATTENDANCE -->

        <li>
            <a href="attendance.php">

                <span class="menu-icon">
                    📋
                </span>

                <span>
                    Sunday Attendance
                </span>

            </a>
        </li>


        <!-- MONTHLY REPORT -->

        <li>
            <a href="monthly_report.php">

                <span class="menu-icon">
                    📊
                </span>

                <span>
                    Monthly Report
                </span>

            </a>
        </li>

    </ul>

</aside>



<!-- =========================
     MAIN CONTENT
========================= -->

<main class="main-content">

<div class="container">


    <!-- HEADER -->

    <div class="header">

        <h1>
            ⛪ Church Attendance System
        </h1>

        <p>
            Dashboard •
            <?= htmlspecialchars($monthName) ?>
        </p>

    </div>



    <!-- STATISTICS -->

    <div class="stats">


        <div class="stat-card">

            <div class="stat-title">
                👥 Active Members
            </div>

            <div class="stat-number">
                <?= $activeMembers ?>
            </div>

        </div>



        <div class="stat-card">

            <div class="stat-title">
                ✓ Present This Month
            </div>

            <div class="stat-number">
                <?= $presentThisMonth ?>
            </div>

        </div>



        <div class="stat-card">

            <div class="stat-title">
                📊 Attendance Rate
            </div>

            <div class="stat-number">
                <?= number_format($attendanceRate, 1) ?>%
            </div>

        </div>



        <div class="stat-card">

            <div class="stat-title">
                📅 Sundays Recorded
            </div>

            <div class="stat-number">
                <?= $sundaysRecorded ?>
            </div>

        </div>

    </div>



    <!-- LATEST ATTENDANCE -->

    <div class="latest-summary">

        <h2 class="section-title">
            Latest Attendance Summary
        </h2>


        <?php if ($latestAttendanceDate): ?>

            <p>
                Latest recorded attendance:

                <strong>

                    <?= htmlspecialchars(
                        date(
                            'F d, Y',
                            strtotime($latestAttendanceDate)
                        )
                    ) ?>

                </strong>
            </p>


            <div class="latest-grid">


                <div class="latest-card">

                    <h3>
                        ✓ Present
                    </h3>

                    <p class="present">
                        <?= $latestPresent ?>
                    </p>

                </div>



                <div class="latest-card">

                    <h3>
                        ✕ Absent
                    </h3>

                    <p class="absent">
                        <?= $latestAbsent ?>
                    </p>

                </div>



                <div class="latest-card">

                    <h3>
                        Attendance Rate
                    </h3>

                    <p>
                        <?= number_format($latestRate, 1) ?>%
                    </p>

                </div>


            </div>


        <?php else: ?>

            <p class="no-records">
                No attendance has been recorded yet.
            </p>

        <?php endif; ?>

    </div>



    <!-- QUICK ACTIONS -->

    <h2 class="section-title">
        Quick Actions
    </h2>


    <div class="quick-actions">


        <a
            href="attendance.php"
            class="action-card"
        >

            <div class="action-title">
                📋 Sunday Attendance
            </div>

            <div class="action-description">
                Record church member attendance.
            </div>

        </a>



        <a
            href="members.php"
            class="action-card"
        >

            <div class="action-title">
                👥 Manage Members
            </div>

            <div class="action-description">
                Add and manage church members.
            </div>

        </a>



        <a
            href="monthly_report.php"
            class="action-card"
        >

            <div class="action-title">
                📊 Monthly Report
            </div>

            <div class="action-description">
                View attendance records for any month.
            </div>

        </a>


    </div>



    <!-- RECENT ATTENDANCE -->

    <div class="recent-section">

        <h2 class="section-title">
            Recent Attendance
        </h2>


        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>
                            Date
                        </th>

                        <th>
                            Member Code
                        </th>

                        <th>
                            Member
                        </th>

                        <th>
                            Ministry
                        </th>

                        <th>
                            Status
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (count($recentAttendance) > 0): ?>


                    <?php foreach ($recentAttendance as $record): ?>

                    <tr>


                        <td>

                            <?= htmlspecialchars(
                                date(
                                    'F d, Y',
                                    strtotime(
                                        $record['attendance_date']
                                    )
                                )
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $record['member_code']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $record['member_name']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $record['ministry']
                                ?: '—'
                            ) ?>

                        </td>


                        <td>


                            <?php if (
                                $record['status']
                                === 'Present'
                            ): ?>

                                <span class="present">
                                    ✓ Present
                                </span>

                            <?php else: ?>

                                <span class="absent">
                                    ✕ Absent
                                </span>

                            <?php endif; ?>


                        </td>


                    </tr>

                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="5"
                            class="no-records"
                        >

                            No attendance records found.

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>


</div>

</main>


</body>

</html>
```
