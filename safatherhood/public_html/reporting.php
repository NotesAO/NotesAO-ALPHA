<?php
// Include config file
include_once 'auth.php';
check_loggedin($con);
require_once "helpers.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NotesAO - CSV Reporting</title>
    <!-- FAVICON LINKS (from index.html) -->
    <link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/favicons/favicon-96x96.png">
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg">

    <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#211c56">

    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
    <link rel="apple-touch-icon" sizes="167x167" href="/favicons/apple-touch-icon-ipad-pro.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/favicons/apple-touch-icon-ipad.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/favicons/apple-touch-icon-120x120.png">

    <link rel="manifest" href="/favicons/site.webmanifest">
    <meta name="apple-mobile-web-app-title" content="NotesAO">
    <!-- Bootstrap 4.5 and Font Awesome (Ensure these match home.php) -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

</head>
<?php require_once('navbar.php'); ?>
<body>
    <section class="pt-5">
        <div class="container-fluid">
            <div class="row">
                <div class="col-6">
                    <a href="truant_client.php" class="btn btn-primary">Truant Client Report</a>
                </div>
            </div>
            <div class="row">
                <div class="col-2">
                        <p></p>
                </div>
            </div>
            <div class="row">
                <div class="col-6">
                    <a href="mar2.php" class="btn btn-primary">Monthly Activity Report</a>
                </div>
            </div>
            <div class="row">
                <div class="col-2">
                        <p></p>
                </div>
            </div>
            <div class="row">
                <div class="col-2">
                    <h3>Build Report Table</h3>
                </div>
                <div class="col-1">
                    <a href="build_report_table.php" class="btn btn-primary">Generate</a>
                </div>
            </div>
            <div class="row">
                <div class="col-2">
                        <h3>Fetch Report Table</h3>
                </div>
                <div class="col-2">
                    <a href="dump_report_csv.php" class="btn btn-primary">Fetch Report Table as CSV</a>
                </div>
            </div>

            <div class="row">
                <div class="col-2">
                        <p></p>
                </div>
            </div>
            <div class="row">
                <div class="col-2">
                        <h3>Build Report Table 2</h3>
                </div>
                <div class="col-3">
                    <a href="build_report2_table.php" class="btn btn-primary">Generate (No Exits)</a>
                    <a href="build_report2_table.php?include_exits=true" class="btn btn-primary">Generate (With Exits)</a>
                </div>
            </div>

            <div class="row">
                <div class="col-2">
                        <h3>Fetch Report Table 2</h3>
                </div>
                <div class="col-2">
                    <a href="dump_report_csv.php?table_num=2" class="btn btn-primary">Fetch Report Table 2 as CSV</a>
                </div>
            </div>

            <div class="row">
                <div class="col-2">
                        <p></p>
                </div>
            </div>
            <div class="row">
                <div class="col-2">
                        <h3>Build Report Table 3</h3>
                </div>
                <div class="col-3">
                    <a href="build_report3_table.php" class="btn btn-primary">Generate (No Exits)</a>
                    <a href="build_report3_table.php?include_exits=true" class="btn btn-primary">Generate (With Exits)</a>
                </div>
            </div>

            <div class="row">
                <div class="col-2">
                        <h3>Fetch Report Table 3</h3>
                </div>
                <div class="col-2">
                    <a href="dump_report_csv.php?table_num=3" class="btn btn-primary">Fetch Report Table 3 as CSV</a>
                </div>
            </div>

            <div class="row">
                <div class="col-2">
                        <p></p>
                </div>
            </div>
            <div class="row">
                <div class="col-2">
                        <h3>Build Report Table 4</h3>
                </div>
                <div class="col-3">
                    <a href="build_report4_table.php" class="btn btn-primary">Generate (No Exits)</a>
                    <a href="build_report4_table.php?include_exits=true" class="btn btn-primary">Generate (With Exits)</a>
                </div>
            </div>

            <div class="row">
                <div class="col-2">
                        <h3>Fetch Report Table 4</h3>
                </div>
                <div class="col-2">
                    <a href="dump_report_csv.php?table_num=4" class="btn btn-primary">Fetch Report Table 4 as CSV</a>
                </div>
            </div>

            <div class="row">
                <div class="col-2">
                        <p></p>
                </div>
            </div>

            <div class="row">
                <div class="col-2">
                        <h3>Fetch Report Table 5</h3>
                </div>
                <div class="col-2">
                    <a href="dump_report5_csv.php?table_num=5" class="btn btn-primary">Fetch Report Table 5 as CSV</a>
                </div>
            </div>

        </div>
    </section>
<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
</body>
</html>
