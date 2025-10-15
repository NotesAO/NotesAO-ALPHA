<?php
/* ───────────────────────────────
   Shared sticky site header
   To include:  <?php include $_SERVER['DOCUMENT_ROOT'].'/partials/header.php'; ?>
   ─────────────────────────────── */
?>
<header class="site-header">
  <a href="/" class="logo">
    <img src="/assets/images/NotesAO Logo.png" alt="NotesAO logo">
  </a>

  <nav class="main-nav" role="navigation" aria-label="Primary">
    <ul class="nav-list">

      <!-- HOME ▼ -->
      <li class="dropdown">
        <button class="drop-toggle" aria-expanded="false">Home</button>
        <ul class="dropdown-menu">
          <li><a href="/#features">Features</a></li>
          <li><a href="/#testimonials">Testimonials</a></li>
        </ul>
      </li>

      <li><a href="/login.php">Login</a></li>
      <li><a href="/signup.php">Sign&nbsp;Up</a></li>

      <!-- LEGAL ▼ -->
      <li class="dropdown">
        <button class="drop-toggle" aria-expanded="false">Legal</button>
        <ul class="dropdown-menu">
          <li><a href="/legal/privacy.html">Privacy&nbsp;Policy</a></li>
          <li><a href="/legal/terms.html">Terms&nbsp;of&nbsp;Service</a></li>
          <li><a href="/legal/accessibility.html">Accessibility</a></li>
          <li><a href="/legal/security.html">Security</a></li>
        </ul>
      </li>

    </ul>
  </nav>
</header>
