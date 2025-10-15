<?php
/* =========================================================
   NotesAO – global (cross-clinic) settings
   ========================================================= */

/* === SMTP for transactional mail === */
define('smtp_host',   'webmail.notesao.com');  // e.g. mail.notesao.com
define('smtp_user',   'no-reply@notesao.com');
define('smtp_pass',   'TB~f!pyM[9Em');
define('smtp_port',   587);               // 587 = STARTTLS, 465 = SSL
define('smtp_secure', 'tls');            // 'tls' or 'ssl'
define('smtp_from',   'no-reply@notesao.com');
define('smtp_from_name', 'NotesAO');

/* === (optional) company-wide constants you might use later === */
// define('company_name', 'NotesAO');
// define('support_email', 'support@notesao.com');
