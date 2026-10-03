<?php

session_start();
require_once "config/database.php";

/* =========================================================
   PROTECT PAGE
   ========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}


/* =========================================================
   SELECTED MONTH
   ========================================================= */

$selectedMonth = $_GET["month"] ?? date("Y-m");

$monthDate = DateTime::createFromFormat(
    "Y-m",
    $selectedMonth
);

if (!$monthDate) {

    $selectedMonth = date("Y-m");

    $monthDate = DateTime::createFromFormat(
        "Y-m",
        $selectedMonth
    );

}

$monthName = $monthDate->format("F Y");

$startDate = $selectedMonth . "-01";

$endDate = date(
    "Y-m-d",
    strtotime($startDate . " +1 month")
);


/* =========================================================
   SEARCH MEMBER
   ========================================================= */

$search = trim($_GET["search"] ?? "");


/* =========================================================
   TOTAL ACTIVE MEMBERS
   ========================================================= */

$stmt = $conn->query("
    SELECT COUNT(*)
    FROM members
    WHERE status = 'Active'
");

$totalActiveMembers = (int)$stmt->fetchColumn();


/* =========================================================
   TOTAL PRESENT RECORDS
   ========================================================= */

$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM attendance
    WHERE status = 'Present'
    AND attendance_date >= ?
    AND attendance_date < ?
");

$stmt->execute([
    $startDate,
    $endDate
]);

$totalPresent = (int)$stmt->fetchColumn();


/* =========================================================
   TOTAL ABSENT RECORDS
   ========================================================= */

$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM attendance
    WHERE status = 'Absent'
    AND attendance_date >= ?
    AND attendance_date < ?
");

$stmt->execute([
    $startDate,
    $endDate
]);

$totalAbsent = (int)$stmt->fetchColumn();


/* =========================================================
   TOTAL ATTENDANCE RECORDS
   ========================================================= */

$totalRecords = $totalPresent + $totalAbsent;


/* =========================================================
   OVERALL ATTENDANCE RATE
   ========================================================= */

if ($totalRecords > 0) {

    $overallRate =
        ($totalPresent / $totalRecords) * 100;

} else {

    $overallRate = 0;

}


/* =========================================================
   SUNDAYS WITH RECORDED ATTENDANCE
   ========================================================= */

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT attendance_date)
    FROM attendance
    WHERE attendance_date >= ?
    AND attendance_date < ?
");

$stmt->execute([
    $startDate,
    $endDate
]);

$sundaysRecorded = (int)$stmt->fetchColumn();


/* =========================================================
   GET MEMBER ATTENDANCE SUMMARY
   ========================================================= */

$sql = "
    SELECT

        m.id,
        m.member_code,
        m.first_name,
        m.middle_name,
        m.last_name,
        m.gender,
        m.ministry,

        COALESCE(
            SUM(
                CASE
                    WHEN a.status = 'Present'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS present_count,

        COALESCE(
            SUM(
                CASE
                    WHEN a.status = 'Absent'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS absent_count

    FROM members m

    LEFT JOIN attendance a
        ON a.member_id = m.id
        AND a.attendance_date >= ?
        AND a.attendance_date < ?

    WHERE m.status = 'Active'
";

$params = [
    $startDate,
    $endDate
];


/* =========================================================
   SEARCH FILTER
   ========================================================= */

if ($search !== "") {

    $sql .= "
        AND (
            m.member_code LIKE ?
            OR m.first_name LIKE ?
            OR m.middle_name LIKE ?
            OR m.last_name LIKE ?
            OR m.ministry LIKE ?
        )
    ";

    $keyword = "%" . $search . "%";

    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;

}


/* =========================================================
   GROUP AND ORDER
   ========================================================= */

$sql .= "
    GROUP BY
        m.id,
        m.member_code,
        m.first_name,
        m.middle_name,
        m.last_name,
        m.gender,
        m.ministry

    ORDER BY
        m.last_name ASC,
        m.first_name ASC
";

$stmt = $conn->prepare($sql);

$stmt->execute($params);

$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"


<title>Monthly Report | Church Attendance</title>


<style>

/* =========================================================
   GENERAL
   ========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, Helvetica, sans-serif;
}

body {
    background: #f4f6f8;
    color: #17212b;
}


/* =========================================================
   SIDEBAR
   ========================================================= */

.sidebar {
    position: fixed;
    left: 0;
    top: 0;

    width: 240px;
    height: 100vh;

    background: #17212b;
    color: white;

    padding: 25px 15px;
}

.brand {
    text-align: center;
    margin-bottom: 35px;
}

.brand-icon {
    font-size: 35px;
}

.brand h2 {
    font-size: 18px;
    margin-top: 8px;
}

.brand p {
    font-size: 11px;
    color: #aeb8c2;
    margin-top: 5px;
}

.menu {
    list-style: none;
}

.menu li {
    margin-bottom: 8px;
}

.menu a {
    display: block;
    padding: 13px 15px;

    border-radius: 8px;

    color: #dbe3ea;
    text-decoration: none;

    transition: 0.2s;
}

.menu a:hover,
.menu a.active {
    background: #2d3e4d;
    color: white;
}

.logout {
    position: absolute;

    bottom: 25px;
    left: 15px;
    right: 15px;
}

.logout a {
    display: block;

    padding: 12px;

    text-align: center;

    border-radius: 8px;

    background: #263746;

    color: white;
    text-decoration: none;
}


/* =========================================================
   MAIN
   ========================================================= */

.main {
    margin-left: 240px;
    padding: 30px;
}


/* =========================================================
   HEADER
   ========================================================= */

.report-header {
    display: flex;

    justify-content: space-between;
    align-items: center;

    margin-bottom: 25px;

    flex-wrap: wrap;

    gap: 15px;
}

.report-header h1 {
    font-size: 28px;
}

.report-header p {
    color: #6b7280;
    margin-top: 6px;
}


/* =========================================================
   PRINT BUTTON
   ========================================================= */

.print-btn {
    padding: 12px 18px;

    background: #17212b;
    color: white;

    border: none;
    border-radius: 8px;

    font-weight: bold;
    cursor: pointer;
}

.print-btn:hover {
    background: #34495e;
}


/* =========================================================
   FILTER CARD
   ========================================================= */

.filter-card {
    background: white;

    padding: 20px;

    border-radius: 14px;

    margin-bottom: 25px;

    box-shadow:
        0 3px 12px rgba(0,0,0,0.06);
}

.filter-form {
    display: flex;

    gap: 12px;
    align-items: end;

    flex-wrap: wrap;
}

.form-field {
    flex: 1;
    min-width: 180px;
}

.form-field label {
    display: block;

    font-size: 12px;
    font-weight: bold;

    margin-bottom: 7px;

    color: #4b5563;
}

.form-field input {
    width: 100%;

    padding: 11px;

    border: 1px solid #ddd;

    border-radius: 8px;

    outline: none;
}

.filter-btn {
    padding: 11px 18px;

    border: none;

    border-radius: 8px;

    background: #0877c9;
    color: white;

    font-weight: bold;
    cursor: pointer;
}


/* =========================================================
   STATISTICS
   ========================================================= */

.stats {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}

.stat-card {
    background: white;

    padding: 22px;

    border-radius: 14px;

    box-shadow:
        0 3px 12px rgba(0,0,0,0.06);
}

.stat-label {
    font-size: 12px;

    color: #6b7280;

    margin-bottom: 10px;
}

.stat-number {
    font-size: 28px;

    font-weight: bold;

    color: #17212b;
}


/* =========================================================
   ATTENDANCE PROGRESS
   ========================================================= */

.rate-card {
    background: white;

    padding: 22px;

    border-radius: 14px;

    margin-bottom: 25px;

    box-shadow:
        0 3px 12px rgba(0,0,0,0.06);
}

.rate-top {
    display: flex;

    justify-content: space-between;

    margin-bottom: 12px;

    font-size: 14px;

    font-weight: bold;
}

.progress {
    width: 100%;
    height: 13px;

    background: #e5e7eb;

    border-radius: 20px;

    overflow: hidden;
}

.progress-fill {
    height: 100%;

    background: linear-gradient(
        90deg,
        #0877c9,
        #16a34a
    );

    border-radius: 20px;

    width: <?= min(100, max(0, $overallRate)) ?>%;
}


/* =========================================================
   REPORT TABLE
   ========================================================= */

.report-card {
    background: white;

    padding: 22px;

    border-radius: 14px;

    box-shadow:
        0 3px 12px rgba(0,0,0,0.06);
}

.report-card h2 {
    font-size: 20px;
    margin-bottom: 6px;
}

.report-card p {
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 20px;
}

.table-container {
    overflow-x: auto;
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 850px;
}

th {
    background: #f8fafc;

    color: #6b7280;

    text-align: left;

    padding: 14px;

    font-size: 12px;

    border-bottom: 1px solid #e5e7eb;
}

td {
    padding: 14px;

    font-size: 13px;

    border-bottom: 1px solid #f0f1f2;
}

tr:hover {
    background: #fafcff;
}

.member-code {
    font-weight: bold;
    color: #34495e;
}

.present {
    color: #15803d;
    font-weight: bold;
}

.absent {
    color: #dc2626;
    font-weight: bold;
}


/* =========================================================
   PERFORMANCE BADGES
   ========================================================= */

.badge {
    display: inline-block;

    padding: 6px 10px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: bold;
}

.excellent {
    background: #dcfce7;
    color: #166534;
}

.good {
    background: #dbeafe;
    color: #1d4ed8;
}

.average {
    background: #fef3c7;
    color: #92400e;
}

.low {
    background: #fee2e2;
    color: #991b1b;
}

.no-record {
    background: #e5e7eb;
    color: #4b5563;
}


/* =========================================================
   NO DATA
   ========================================================= */

.no-data {
    text-align: center;

    padding: 35px;

    color: #777;
}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 1000px) {

    .stats {
        grid-template-columns:
            repeat(2, 1fr);
    }

}

@media (max-width: 800px) {

    .sidebar {
        position: relative;

        width: 100%;
        height: auto;
    }

    .logout {
        position: relative;

        left: auto;
        right: auto;
        bottom: auto;

        margin-top: 20px;
    }

    .main {
        margin-left: 0;
        padding: 20px;
    }

}

@media (max-width: 550px) {

    .stats {
        grid-template-columns: 1fr;
    }

    .report-header h1 {
        font-size: 23px;
    }

}


/* =========================================================
   PRINT
   ========================================================= */

@media print {

    .sidebar,
    .filter-card,
    .print-btn {
        display: none !important;
    }

    body {
        background: white;
    }

    .main {
        margin: 0;
        padding: 0;
    }

    .stat-card,
    .rate-card,
    .report-card {
        box-shadow: none;
        border: 1px solid #ddd;
    }

}

</style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
     ========================================================= -->

<aside class="sidebar">

    <div class="brand">

        <div class="brand-icon">
            ⛪
        </div>

        <h2>Church Attendance</h2>

        <p>Monitoring System</p>

    </div>


    <ul class="menu">

        <li>
            <a href="dashboard.php">
                🏠 Dashboard
            </a>
        </li>

        <li>
            <a href="members.php">
                👥 Members
            </a>
        </li>

        <li>
            <a href="attendance.php">
                📅 Sunday Attendance
            </a>
        </li>

        <li>
            <a
                href="monthly_report.php"
                class="active"
            >
                📊 Monthly Report
            </a>
        </li>

    </ul>


    <div class="logout">

        <a href="logout.php">
            🚪 Logout
        </a>

    </div>

</aside>


<!-- =========================================================
     MAIN CONTENT
     ========================================================= -->

<main class="main">


    <!-- HEADER -->

    <div class="report-header">

        <div>

            <h1>
                Monthly Attendance Report
            </h1>

            <p>

                Detailed attendance summary for

                <strong>
                    <?= htmlspecialchars($monthName) ?>
                </strong>

            </p>

        </div>


        <button
            class="print-btn"
            onclick="window.print()"
        >
            🖨️ Print Report
        </button>

    </div>


    <!-- =====================================================
         FILTER
         ===================================================== -->

    <div class="filter-card">

        <form
            method="GET"
            class="filter-form"
        >

            <div class="form-field">

                <label>
                    Select Month
                </label>

                <input
                    type="month"
                    name="month"
                    value="<?= htmlspecialchars($selectedMonth) ?>"
                >

            </div>


            <div class="form-field">

                <label>
                    Search Member
                </label>

                <input
                    type="text"
                    name="search"
                    placeholder="Name, ID, or ministry..."
                    value="<?= htmlspecialchars($search) ?>"
                >

            </div>


            <button
                type="submit"
                class="filter-btn"
            >
                🔍 View Report
            </button>

        </form>

    </div>


    <!-- =====================================================
         STATISTICS
         ===================================================== -->

    <div class="stats">


        <div class="stat-card">

            <div class="stat-label">
                Active Members
            </div>

            <div class="stat-number">
                <?= $totalActiveMembers ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Present Records
            </div>

            <div class="stat-number">
                <?= $totalPresent ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Absent Records
            </div>

            <div class="stat-number">
                <?= $totalAbsent ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Sundays Recorded
            </div>

            <div class="stat-number">
                <?= $sundaysRecorded ?>
            </div>

        </div>


    </div>


    <!-- =====================================================
         ATTENDANCE RATE
         ===================================================== -->

    <div class="rate-card">

        <div class="rate-top">

            <span>
                Overall Attendance Rate
            </span>

            <span>
                <?= number_format($overallRate, 1) ?>%
            </span>

        </div>


        <div class="progress">

            <div class="progress-fill"></div>

        </div>

    </div>


    <!-- =====================================================
         MEMBER REPORT
         ===================================================== -->

    <div class="report-card">

        <h2>
            Member Attendance Summary
        </h2>

        <p>
            Individual attendance performance for <?= htmlspecialchars($monthName) ?>.
        </p>


        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>Member ID</th>

                        <th>Member Name</th>

                        <th>Ministry</th>

                        <th>Present</th>

                        <th>Absent</th>

                        <th>Attendance Rate</th>

                        <th>Performance</th>

                    </tr>

                </thead>


                <tbody>


                <?php if (count($members) > 0): ?>


                    <?php foreach ($members as $member): ?>


                        <?php

                        $fullName =
                            $member["first_name"];

                        if (
                            !empty(
                                $member["middle_name"]
                            )
                        ) {

                            $fullName .=
                                " "
                                . $member["middle_name"];

                        }

                        $fullName .=
                            " "
                            . $member["last_name"];


                        $present =
                            (int)$member["present_count"];

                        $absent =
                            (int)$member["absent_count"];

                        $memberTotal =
                            $present + $absent;


                        if ($memberTotal > 0) {

                            $memberRate =
                                ($present / $memberTotal)
                                * 100;

                        } else {

                            $memberRate = 0;

                        }


                        /* PERFORMANCE */

                        if ($memberTotal === 0) {

                            $performance =
                                "No Record";

                            $badgeClass =
                                "no-record";

                        } elseif ($memberRate >= 90) {

                            $performance =
                                "Excellent";
                                $badgeClass =
                                "excellent";

                        } elseif ($memberRate >= 75) {

                            $performance =
                                "Good";

                            $badgeClass =
                                "good";

                        } elseif ($memberRate >= 50) {

                            $performance =
                                "Average";

                            $badgeClass =
                                "average";

                        } else {

                            $performance =
                                "Needs Improvement";

                            $badgeClass =
                                "low";

                        }

                        ?>


                        <tr>


                            <td class="member-code">

                                <?= htmlspecialchars(
                                    $member["member_code"]
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $fullName
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $member["ministry"]
                                    ?: "—"
                                ) ?>

                            </td>


                            <td class="present">

                                <?= $present ?>

                            </td>


                            <td class="absent">

                                <?= $absent ?>

                            </td>


                            <td>

                                <strong>

                                    <?= number_format(
                                        $memberRate,
                                        1
                                    ) ?>%

                                </strong>

                            </td>


                            <td>

                                <span
                                    class="badge <?= $badgeClass ?>"
                                >

                                    <?= $performance ?>

                                </span>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="7"
                            class="no-data"
                        >

                            No active members found for this report.

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>


</main>


</body>

</html>