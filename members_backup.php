<?php
session_start();
require_once "config/database.php";

/* Protect page */
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$message = "";
$error = "";

/* ==========================
   ADD MEMBER
   ========================== */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_member"])) {

    $first_name = trim($_POST["first_name"]);
    $middle_name = trim($_POST["middle_name"]);
    $last_name = trim($_POST["last_name"]);
    $gender = $_POST["gender"];
    $birth_date = !empty($_POST["birth_date"]) ? $_POST["birth_date"] : null;
    $contact_number = trim($_POST["contact_number"]);
    $ministry = trim($_POST["ministry"]);

    if ($first_name === "" || $last_name === "" || $gender === "") {
        $error = "Please fill in all required fields.";
    } else {

        /* Generate member code */

        $stmt = $conn->query("
            SELECT COUNT(*) 
            FROM members
        ");

        $count = $stmt->fetchColumn() + 1;

        $member_code = "MEM-" . str_pad($count, 3, "0", STR_PAD_LEFT);

        try {

            $stmt = $conn->prepare("
                INSERT INTO members
                (
                    member_code,
                    first_name,
                    middle_name,
                    last_name,
                    gender,
                    birth_date,
                    contact_number,
                    ministry,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')
            ");

            $stmt->execute([
                $member_code,
                $first_name,
                $middle_name ?: null,
                $last_name,
                $gender,
                $birth_date,
                $contact_number,
                $ministry
            ]);

            $message = "Member added successfully!";

        } catch (PDOException $e) {

            $error = "Unable to add member.";

        }
    }
}


/* ==========================
   SEARCH
   ========================== */

$search = trim($_GET["search"] ?? "");

if ($search !== "") {

    $stmt = $conn->prepare("
        SELECT *
        FROM members
        WHERE
            member_code LIKE ?
            OR first_name LIKE ?
            OR middle_name LIKE ?
            OR last_name LIKE ?
            OR ministry LIKE ?
        ORDER BY last_name ASC
    ");

    $keyword = "%" . $search . "%";

    $stmt->execute([
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword
    ]);

} else {

    $stmt = $conn->query("
        SELECT *
        FROM members
        ORDER BY last_name ASC
    ");
}

$members = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ==========================
   TOTAL ACTIVE MEMBERS
   ========================== */

$stmt = $conn->query("
    SELECT COUNT(*)
    FROM members
    WHERE status = 'Active'
");

$totalActive = $stmt->fetchColumn();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Members | Church Attendance</title>

<style>

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


/* =========================
   SIDEBAR
   ========================= */

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
.menu .active {
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


/* =========================
   MAIN
   ========================= */

.main {
    margin-left: 240px;

    padding: 30px;
}


/* =========================
   HEADER
   ========================= */

.header {
    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;
}

.header h1 {
    font-size: 28px;
}

.header p {
    color: #6b7280;

    margin-top: 5px;
}


/* =========================
   ADD BUTTON
   ========================= */

.add-btn {
    background: #17212b;

    color: white;

    border: none;

    padding: 13px 18px;

    border-radius: 9px;

    cursor: pointer;

    font-weight: bold;
}

.add-btn:hover {
    background: #34495e;
}


/* =========================
   ALERT
   ========================= */

.alert {
    padding: 13px;

    border-radius: 8px;

    margin-bottom: 20px;

    background: #dcfce7;

    color: #166534;
}

.error {
    padding: 13px;

    border-radius: 8px;

    margin-bottom: 20px;

    background: #fee2e2;

    color: #991b1b;
}


/* =========================
   SEARCH + STATS
   ========================= */

.toolbar {
    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 20px;
}

.search-box {
    display: flex;

    gap: 8px;
}

.search-box input {
    width: 280px;

    padding: 12px;

    border: 1px solid #ddd;

    border-radius: 8px;

    outline: none;
}

.search-box button {
    padding: 12px 18px;

    border: none;

    border-radius: 8px;

    background: #34495e;

    color: white;

    cursor: pointer;
}

.stats {
    background: white;

    padding: 15px 20px;

    border-radius: 10px;

    box-shadow: 0 3px 12px rgba(0,0,0,0.05);
}

.stats strong {
    font-size: 22px;
}


/* =========================
   TABLE
   ========================= */

.table-container {
    background: white;

    border-radius: 14px;

    overflow-x: auto;

    box-shadow: 0 4px 15px rgba(0,0,0,0.06);
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 850px;
}

th {
    text-align: left;

    padding: 15px;

    font-size: 12px;

    color: #6b7280;

    background: #fafbfc;

    border-bottom: 1px solid #e5e7eb;
}

td {
    padding: 15px;

    border-bottom: 1px solid #f0f1f2;

    font-size: 13px;
}

.member-code {
    font-weight: bold;

    color: #34495e;
}

.status {
    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: bold;
}

.active {
    background: #dcfce7;

    color: #166534;
}

.inactive {
    background: #e5e7eb;

    color: #4b5563;
}


/* =========================
   MODAL
   ========================= */

.modal {
    display: none;

    position: fixed;

    inset: 0;

    background: rgba(0,0,0,0.5);

    justify-content: center;

    align-items: center;

    padding: 20px;
}

.modal-content {
    background: white;

    width: 100%;

    max-width: 600px;

    border-radius: 15px;

    padding: 30px;

    max-height: 90vh;

    overflow-y: auto;
}

.modal-header {
    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;
}

.close {
    font-size: 25px;

    cursor: pointer;

    color: #777;
}

.form-grid {
    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 15px;
}

.form-group {
    margin-bottom: 10px;
}

.form-group.full {
    grid-column: 1 / -1;
}

label {
    display: block;

    margin-bottom: 7px;

    font-size: 13px;

    font-weight: bold;
}

input,
select {
    width: 100%;

    padding: 12px;

    border: 1px solid #ddd;

    border-radius: 8px;

    outline: none;
}

.submit-btn {
    width: 100%;

    padding: 13px;

    border: none;

    border-radius: 8px;

    background: #17212b;

    color: white;

    font-weight: bold;

    cursor: pointer;

    margin-top: 15px;
}


/* =========================
   MOBILE
   ========================= */

@media(max-width: 800px) {

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

    .header {
        display: block;
    }

    .add-btn {
        margin-top: 15px;
    }

    .toolbar {
        display: block;
    }

    .search-box {
        margin-bottom: 15px;
    }

    .search-box input {
        width: 100%;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full {
        grid-column: auto;
    }

}

</style>

</head>


<body>


<!-- =========================
     SIDEBAR
     ========================= -->

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
            <a href="members.php" class="active">
                👥 Members
            </a>
        </li>

        <li>
            <a href="attendance.php">
                📅 Sunday Attendance
            </a>
        </li>

        <li>
            <a href="monthly_report.php">
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


<!-- =========================
     MAIN
     ========================= -->

<main class="main">


    <div class="header">

        <div>

            <h1>Church Members</h1>

            <p>
                Manage your church members and their information.
            </p>

        </div>


        <button
            class="add-btn"
            onclick="openModal()"
        >
            + Add Member
        </button>

    </div>


    <?php if ($message): ?>

        <div class="alert">

            <?php echo htmlspecialchars($message); ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="error">

            <?php echo htmlspecialchars($error); ?>

        </div>

    <?php endif; ?>


    <div class="toolbar">


        <form
            method="GET"
            class="search-box"
        >

            <input
                type="text"
                name="search"
                placeholder="Search member..."
                value="<?php echo htmlspecialchars($search); ?>"
            >

            <button type="submit">
                Search
            </button>

        </form>


        <div class="stats">

            Active Members:

            <strong>
                <?php echo $totalActive; ?>
            </strong>

        </div>


    </div>


    <!-- =========================
         MEMBERS TABLE
         ========================= -->

    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>Member ID</th>

                    <th>Name</th>

                    <th>Gender</th>

                    <th>Contact</th>

                    <th>Ministry</th>

                    <th>Status</th>

                </tr>

            </thead>


            <tbody>

            <?php if (count($members) > 0): ?>

                <?php foreach ($members as $member): ?>

                    <tr>

                        <td class="member-code">

                            <?php
                            echo htmlspecialchars(
                                $member["member_code"]
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo htmlspecialchars(
                                $member["first_name"]
                                . " "
                                . ($member["middle_name"]
                                    ? $member["middle_name"]
                                    . " "
                                    : "")
                                . $member["last_name"]
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo htmlspecialchars(
                                $member["gender"]
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo htmlspecialchars(
                                $member["contact_number"]
                                ?: "—"
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo htmlspecialchars(
                                $member["ministry"]
                                ?: "—"
                            );
                            ?>

                        </td>


                        <td>

                            <span class="status
                            <?php
                            echo strtolower(
                                $member["status"]
                            );
                            ?>">

                                <?php
                                echo htmlspecialchars(
                                    $member["status"]
                                );
                                ?>

                            </span>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="6"
                        style="text-align:center;padding:30px;"
                    >

                        No members found.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>


</main>


<!-- =========================
     ADD MEMBER MODAL
     ========================= -->

<div
    class="modal"
    id="memberModal"


    <div class="modal-content">


        <div class="modal-header">

            <h2>Add New Member</h2>

            <span
                class="close"
                onclick="closeModal()"
            >
                ×
            </span>

        </div>


        <form method="POST">


            <div class="form-grid">


                <div class="form-group">

                    <label>
                        First Name *
                    </label>

                    <input
                        type="text"
                        name="first_name"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Middle Name
                    </label>

                    <input
                        type="text"
                        name="middle_name"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Last Name *
                    </label>

                    <input
                        type="text"
                        name="last_name"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Gender *
                    </label>

                    <select
                        name="gender"
                        required
                    >

                        <option value="">
                            Select Gender
                        </option>

                        <option value="Male">
                            Male
                        </option>

                        <option value="Female">
                            Female
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Birth Date
                    </label>

                    <input
                        type="date"
                        name="birth_date"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Contact Number
                    </label>

                    <input
                        type="text"
                        name="contact_number"
                        placeholder="09XXXXXXXXX"
                    >

                </div>


                <div class="form-group full">

                    <label>
                        Ministry / Group
                    </label>

                    <input
                        type="text"
                        name="ministry"
                        placeholder="e.g. Youth Ministry"
                    >

                </div>


            </div>


            <button
                type="submit"
                name="add_member"
                class="submit-btn"
            >
                Save Member
            </button>


        </form>


    </div>

</div>


<script>

function openModal() {

    document.getElementById(
        "memberModal"
    ).style.display = "flex";

}


function closeModal() {

    document.getElementById(
        "memberModal"
    ).style.display = "none";

}


/* Close when clicking outside */

window.onclick = function(event) {

    const modal =
        document.getElementById("memberModal");

    if (event.target === modal) {

        modal.style.display = "none";

    }

}

</script>


</body>

</html>