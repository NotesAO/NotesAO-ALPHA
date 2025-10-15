<?php
    include_once 'auth.php';
    check_loggedin($con);

    require_once "../config/config.php";
    require_once "helpers.php";

    $messageData = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NotesAO - Messaging</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicons -->
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

    <!-- CSS -->
    <link rel="stylesheet"
          href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css"
          crossorigin="anonymous">
    <!-- Font Awesome (ensures icons render even if navbar.php is included after </head>) -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
          crossorigin="anonymous">

    <style>
      /* ensures content doesn't sit under fixed-top navbar if navbar’s own CSS isn’t loaded yet */
      body { padding-top: 56px; }
    </style>
</head>
<body>

<?php require_once 'navbar.php'; ?>

<section class="pt-2">
  <div class="container-fluid">
    <div class="row">
      <div class="col">
        <div class="page-header">
          <h2><i class="fas fa-envelope-open-text"></i> Messaging Results</h2>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col">
        <?php
          echo "<table class='table table-bordered table-striped'>";
          echo "<thead>";
          echo "<tr>";
          echo "<th>number</th>";
          echo "<th>name</th>";
          echo "<th>message_id</th>";
          echo "</tr>";
          echo "</thead>";
          echo "<tbody>";
          foreach ($messageData as $row) {
            echo "<tr>";
            foreach ($row as $value){
              echo "<td>" . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . "</td>";
            }
            echo "</tr>";
          }
          echo "</tbody>";
          echo "</table>";
        ?>
      </div>
    </div>

    <div class="row">
      <div class="col-1">
        <a href="index.php" class="btn btn-dark">
          <i class="fas fa-check"></i> Done
        </a>
      </div>
    </div>
  </div>
</section>

<!-- JS (Bootstrap dropdowns, navbar scripts) -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
