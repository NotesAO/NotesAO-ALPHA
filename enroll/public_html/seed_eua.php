<?php
declare(strict_types=1);

// Use the enroll config (NOT adminclinic’s)
require __DIR__ . '/../config/config.php'; // defines $db (PDO)

try {
  $path = __DIR__.'/_agreements/eua.html';
  if (!is_file($path)) {
    throw new RuntimeException("Missing $path");
  }
  $html = file_get_contents($path);
  if ($html === false || trim($html) === '') {
    throw new RuntimeException("eua.html is empty or unreadable");
  }
  $sha = hash('sha256', $html);
  $ver = 'EUA-'.date('Y-m-d');

  // Ensure table exists (safe to re-run)
  $db->exec("
    CREATE TABLE IF NOT EXISTS consent_document (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      doc_type ENUM('EUA','PAYMENT_AUTH') NOT NULL,
      version_label VARCHAR(32) NOT NULL,
      html MEDIUMTEXT NOT NULL,
      sha256 CHAR(64) NOT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uq_type_ver (doc_type, version_label)
    ) ENGINE=InnoDB;
  ");

  // Insert (ignore if same version already present)
  $stmt = $db->prepare("INSERT IGNORE INTO consent_document
    (doc_type, version_label, html, sha256) VALUES ('EUA', ?, ?, ?)");
  $stmt->execute([$ver, $html, $sha]);

  if ($stmt->rowCount() === 0) {
    echo "EUA already seeded for version $ver\n";
  } else {
    echo "Seeded EUA as version $ver\n";
  }
} catch (Throwable $e) {
  http_response_code(500);
  file_put_contents(__DIR__.'/seed_eua_error.log',
    date('c').' '.$e->getMessage()."\n".$e->getTraceAsString()."\n\n",
    FILE_APPEND
  );
  echo "Error: ".$e->getMessage();
}
