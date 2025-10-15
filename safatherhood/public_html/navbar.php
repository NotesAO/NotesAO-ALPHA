<?php
include_once 'auth.php';
include_once '../config/config.php';
check_loggedin($con, '../index.php');

// Set fallback for program_name if it's not set in the session
$program_name = $_SESSION['program_name'];
$username     = $_SESSION['name'] ?? 'User';
?>

<!-- Font Awesome CSS link (example using CDN) -->
<link rel="stylesheet" 
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
      integrity="sha512-dLcz9Da+7Ry9p07Cq9pNFIK8vCPN5BqkCXnRxqcyHjOMhJKAocT+h7b8ySBxsp6pc1p6ZC4gnM3G5Ts6zGx0ZQ=="
      crossorigin="anonymous" />

<!-- Example: darker navbar background, light text -->

<style>
  body { padding-top: 50px; }

  /* OUTER menu: allow fly-outs to escape */
  .navbar .dropdown-menu{
    overflow: visible !important;   /* ← key fix */
  }

  /* INNER scroll area: keep your tall menu scrollable */
  .navbar .menu-scroll{
    max-height: 80vh;
    overflow-y: auto;
    overflow-x: visible;            /* let fly-out pass horizontally */
  }

  /* Fly-out submenu */
  .dropdown-submenu{ position: relative; }
  .dropdown-submenu > .dropdown-menu{
    position: absolute;
    top: 0;
    left: 100%;
    margin-top: -0.25rem;
    margin-left: .1rem;
    display: none;
    z-index: 2000;                  /* on top of parent menu */
    min-width: 14rem;
  }
  @media (hover:hover){
    .dropdown-submenu:hover > .dropdown-menu{ display:block; }
  }
  /* Flip left when .dropstart is present */
  .dropdown-submenu.dropstart > .dropdown-menu{
    left: auto;
    right: 100%;
    margin-left: 0;
    margin-right: .1rem;
  }
</style>





<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <a class="navbar-brand nav-link disabled" href="#">
        <?php echo htmlspecialchars("SAF - Current Program: " . $program_name); ?>
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse"
            data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
            aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Collapsible section -->
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav mr-auto">
            <!-- Dropdown Menu -->
            <li class="nav-item dropdown">
                <!-- More descriptive dropdown toggle label -->
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown"
                   role="button" data-toggle="dropdown" aria-haspopup="true"
                   aria-expanded="false">
                    <i class="fas fa-bars"></i> Navigate
                </a>

                <!-- Dropdown items: add padding class p-3 to give it more space -->
                <div class="dropdown-menu p-3" aria-labelledby="navbarDropdown">
                    <h6 class="dropdown-header">Main</h6>
                    <a class="dropdown-item" href="home.php">
                        <i class="fas fa-home"></i> Home
                    </a>
                    <a class="dropdown-item" href="program-select.php">
                        <i class="fas fa-chalkboard-teacher"></i> Program Selection
                    </a>
                    <div class="dropdown-divider"></div>

                    <h6 class="dropdown-header">Client & Sessions</h6>
                    <a class="dropdown-item" href="check_in_step1.php">
                        <i class="fas fa-user-check"></i> Check In
                    </a>
                    <a class="dropdown-item" href="client-index.php">
                        <i class="fas fa-users"></i> Clients
                    </a>
                    <!-- ✱ NEW: Intake packets quick‑view -->
                    <a class="dropdown-item" href="intake-index.php">
                        <i class="fas fa-file-signature"></i> Intake Packets
                    </a>
                    <a class="dropdown-item" href="therapy_session-index.php">
                        <i class="fas fa-clipboard-list"></i> Sessions
                    </a>
                    <a class="dropdown-item" href="case_manager-index.php">
                        <i class="fas fa-user-tie"></i> Case Managers
                    </a>
                    <a class="dropdown-item" href="curriculum-index.php">
                        <i class="fas fa-book"></i> Curriculum
                    </a>

                    <div class="dropdown-divider"></div>
                    <h6 class="dropdown-header">Reporting</h6>

                    <!-- Report Generator above Export CSV -->
                    <a class="dropdown-item" href="reportgen.php">
                        <i class="fas fa-print"></i> Report Generator
                    </a>

                    <!-- Renamed "Reporting" to "Export CSV" -->
                    <a class="dropdown-item" href="reporting.php">
                        <i class="fas fa-file-export"></i> Export CSV
                    </a>


                    <?php
                        if ($_SESSION['role'] === 'Admin') {
                        echo '<div class="dropdown-divider"></div>';
                        echo '<h6 class="dropdown-header">Admin Only</h6>';
                        echo '<a class="dropdown-item" href="client-event-add.php"><i class="fas fa-bullhorn"></i> Client Events (bulk add)</a>';
                        echo '<a class="dropdown-item" href="client-victim-index.php"><i class="fas fa-user-injured"></i> Victims</a>';
                        echo '<a class="dropdown-item" href="document-templates.php"><i class="fas fa-file-alt"></i> Document Templates</a>';
                        echo '<a class="dropdown-item" href="ethnicity-index.php"><i class="fas fa-globe"></i> Ethnicity</a>';
                        echo '<a class="dropdown-item" href="facilitator-index.php"><i class="fas fa-user-graduate"></i> Facilitators</a>';
                        echo '<a class="dropdown-item" href="therapy_group-index.php"><i class="fas fa-users-cog"></i> Groups</a>';
                        echo '<a class="dropdown-item" href="referral_type-index.php"><i class="fas fa-handshake"></i> Referral Types</a>';

                        // ✅ nested submenu
                        echo '<div class="dropdown-submenu">';
                        echo '  <a class="dropdown-item dropdown-toggle" href="#"><i class="fas fa-window-restore"></i> Client Portal</a>';
                        echo '  <div class="dropdown-menu">';
                        // echo '    <a class="dropdown-item" href="payment-link-admin.php"><i class="fas fa-credit-card"></i> Payment Links (CP)</a>';
                        echo '    <a class="dropdown-item" href="clientportal_links_admin.php"><i class="fas fa-video"></i> Zoom Links (CP)</a>';
                        echo '  </div>';
                        echo '</div>';

                        echo '<a class="dropdown-item" href="./admin"><i class="fas fa-tools"></i> Tools</a>';
                        }
                        ?>

                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </li>
        </ul>
    </div>

    <!-- Right side: user info and quick links -->
    <div class="navbar-text mr-3">
        <i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?>
    </div>
    <a class="nav-link text-light" href="home.php">
        <i class="fas fa-home"></i> Home
    </a>
    <span class="text-light"> | </span>
    <a class="nav-link text-light" href="logout.php">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</nav>

<?php // ---- Session heartbeat: auto-redirect on idle timeout ---- ?>
<script>
(function () {
  // Use the server-side TTL so the ping cadence follows your config.
  var SESSION_TTL = <?php echo (int)(defined('SESSION_TTL') ? SESSION_TTL : 1800); ?>;
  // Ping every ~half the TTL, but clamp between 15s and 60s.
  var PING_INTERVAL = Math.min(60000, Math.max(15000, Math.floor(SESSION_TTL * 500)));

  setInterval(function () {
    fetch('/auth.php?__ping=1', { credentials: 'include' })
      .then(function (r) {
        if (r.status === 401) {
          window.location.href = 'https://notesao.com/login.php?timeout=1';
        }
      })
      .catch(function () {
        // ignore transient network errors; next tick will retry
      });
  }, PING_INTERVAL);
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Tap/click to toggle submenu on touch devices
  document.querySelectorAll('.dropdown-submenu > a.dropdown-toggle').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var wrap = this.parentElement;
      var menu = this.nextElementSibling;

      // Auto-flip left if there isn't room on the right
      var rect = wrap.getBoundingClientRect();
      var roomRight = window.innerWidth - rect.right;
      var needsFlip = roomRight < 260; // ~ submenu width
      wrap.classList.toggle('dropstart', needsFlip);

      menu.classList.toggle('show');
      // Hide siblings' submenus
      wrap.parentElement.querySelectorAll('.dropdown-menu.show').forEach(function (m) {
        if (m !== menu) m.classList.remove('show');
      });
    });
  });

  // Close any open submenus when parent dropdown hides
  $('.dropdown').on('hidden.bs.dropdown', function () {
    $(this).find('.dropdown-menu.show').removeClass('show');
    $(this).find('.dropdown-submenu').removeClass('dropstart');
  });
});
</script>

