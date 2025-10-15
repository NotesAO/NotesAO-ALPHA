<?php
/*****************************************************************************
 * NotesAO • navbar.php  (Admin-clinic version with Logout button restored)
 *****************************************************************************/
include_once 'auth.php';
include_once '../config/config.php';
check_loggedin($con, '../index.php');

$username = $_SESSION['name'] ?? 'Admin';
$role     = $_SESSION['role'] ?? 'Admin';
?>
<!-- Font Awesome -->
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
      integrity="sha512-dLcz9Da+7Ry9p07Cq9pNFIK8vCPN5BqkCXnRxqcyHjOMhJKAocT+h7b8ySBxsp6pc1p6ZC4gnM3G5Ts6zGx0ZQ=="
      crossorigin="anonymous">

<style>
  body{ padding-top:56px; }
  .dropdown-menu{ max-height:80vh; overflow-y:auto; }
</style>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
  <!-- Brand -->
  <a class="navbar-brand text-warning font-weight-bold" href="home.php">
    <i class="fas fa-cog"></i> NotesAO&nbsp;Admin
  </a>

  <!-- Burger -->
  <button class="navbar-toggler" type="button" data-toggle="collapse"
          data-target="#adminNav" aria-controls="adminNav"
          aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>

  <!-- Collapsible content -->
  <div class="collapse navbar-collapse" id="adminNav">
    <ul class="navbar-nav mr-auto">

      <!-- NAVIGATE dropdown -->
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="navDrop"
           role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="fas fa-bars"></i> Navigate
        </a>
        <div class="dropdown-menu p-2" aria-labelledby="navDrop">
          <h6 class="dropdown-header">Dashboard</h6>
          <a class="dropdown-item" href="home.php">
            <i class="fas fa-tachometer-alt"></i> Admin Home
          </a>

          <div class="dropdown-divider"></div>
          <h6 class="dropdown-header">Clinics</h6>
          <a class="dropdown-item" href="clinic-index.php">
            <i class="fas fa-clinic-medical"></i> Clinic Index
          </a>
          <a class="dropdown-item" href="clinic-create.php">
            <i class="fas fa-plus-circle"></i> Create New Clinic
          </a>

          <?php if ($role === 'Admin'): ?>
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Tools</h6>
            <a class="dropdown-item" href="./admin">
              <i class="fas fa-tools"></i> Server Tools
            </a>
          <?php endif; ?>

          <div class="dropdown-divider"></div>
          <a class="dropdown-item text-danger" href="logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
          </a>
        </div>
      </li>
    </ul>

    <!-- Right-side: user + logout -->
    <span class="navbar-text text-light mr-2">
      <i class="fas fa-user-circle"></i> <?=htmlspecialchars($username);?>
    </span>
    <a class="btn btn-outline-light btn-sm" href="logout.php" title="Logout">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </div><!-- /.collapse -->
</nav>
