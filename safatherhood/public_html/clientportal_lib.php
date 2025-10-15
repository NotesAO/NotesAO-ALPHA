<?php
// clientportal_lib.php (SAF) — single source of truth for portal links

if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

/** Raw rows (optionally include inactive) */
function cpl_links_list(mysqli $con, bool $include_inactive = false): array {
  $sql = "SELECT * FROM clientportal_links";
  if (!$include_inactive) $sql .= " WHERE is_active=1";
  $sql .= " ORDER BY display_order, id";
  $out = [];
  if ($res = $con->query($sql)) { while ($r = $res->fetch_assoc()) $out[] = $r; $res->free(); }
  return $out;
}

/**
 * Return structure identical to your hardcoded per-link array:
 * [
 *   [
 *     'program_id'=>2,'referral_type_id'=>0,'gender_id'=>2,'required_sessions'=>20,'fee'=>25,
 *     'therapy_group_id'=>3,'label'=>"…",'day_time'=>"…",'join_url'=>"…"
 *   ],
 *   ...
 * ]
 */
function cpl_group_data_for_view(mysqli $con, bool $include_inactive = false): array {
  $rows = cpl_links_list($con, $include_inactive);
  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      'program_id'        => (int)$r['program_id'],
      'referral_type_id'  => (int)$r['referral_type_id'],
      'gender_id'         => (int)$r['gender_id'],
      'required_sessions' => (int)$r['required_sessions'],
      'fee'               => is_null($r['fee']) ? null : (float)$r['fee'],
      'therapy_group_id'  => (int)$r['therapy_group_id'],
      'label'             => (string)$r['label'],
      'day_time'          => (string)$r['day_time'],
      'join_url'          => (string)$r['join_url'],
      // carry-throughs you may want in the future:
      'id'                => (int)$r['id'],
      'is_active'         => (int)$r['is_active'],
      'display_order'     => (int)$r['display_order'],
    ];
  }
  return $out;
}

/** One row */
function cpl_get(mysqli $con, int $id): ?array {
  $st = $con->prepare("SELECT * FROM clientportal_links WHERE id=? LIMIT 1");
  $st->bind_param('i', $id); $st->execute();
  $r = $st->get_result()->fetch_assoc(); $st->close();
  return $r ?: null;
}

/** Insert/Update */
function cpl_save(mysqli $con, array $data, ?int $id, ?string $updated_by = null): int {
  $row = [
    'label'             => trim((string)($data['label'] ?? '')),
    'day_time'          => trim((string)($data['day_time'] ?? '')),
    'join_url'          => trim((string)($data['join_url'] ?? '')),
    'program_id'        => (int)($data['program_id'] ?? 0),
    'referral_type_id'  => (int)($data['referral_type_id'] ?? 0),
    'gender_id'         => (int)($data['gender_id'] ?? 0),
    'required_sessions' => (int)($data['required_sessions'] ?? 0),
    'fee'               => (float)($data['fee'] ?? 0),
    'therapy_group_id'  => (int)($data['therapy_group_id'] ?? 0),
    'is_active'         => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
    'display_order'     => (int)($data['display_order'] ?? 0),
    'notes'             => ($data['notes'] ?? null),
    'updated_by'        => $updated_by,
  ];

  if ($id) {
    $sql = "UPDATE clientportal_links
            SET label=?, day_time=?, join_url=?, program_id=?, referral_type_id=?, gender_id=?,
                required_sessions=?, fee=?, therapy_group_id=?, is_active=?, display_order=?, notes=?, updated_by=?
            WHERE id=?";
    $st = $con->prepare($sql);
    $st->bind_param(
      'sssiiiidiiissi',
      $row['label'], $row['day_time'], $row['join_url'], $row['program_id'], $row['referral_type_id'], $row['gender_id'],
      $row['required_sessions'], $row['fee'], $row['therapy_group_id'], $row['is_active'], $row['display_order'],
      $row['notes'], $row['updated_by'], $id
    );
    $st->execute(); $st->close();
    return $id;
  } else {
    $sql = "INSERT INTO clientportal_links
            (label, day_time, join_url, program_id, referral_type_id, gender_id,
             required_sessions, fee, therapy_group_id, is_active, display_order, notes, updated_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $st = $con->prepare($sql);
    $st->bind_param(
      'sssiiiidiiiss',
      $row['label'], $row['day_time'], $row['join_url'], $row['program_id'], $row['referral_type_id'], $row['gender_id'],
      $row['required_sessions'], $row['fee'], $row['therapy_group_id'], $row['is_active'], $row['display_order'],
      $row['notes'], $row['updated_by']
    );
    $st->execute(); $newId = $st->insert_id; $st->close();
    return (int)$newId;
  }
}

/** Delete row */
function cpl_delete(mysqli $con, int $id): void {
  $st = $con->prepare("DELETE FROM clientportal_links WHERE id=? LIMIT 1");
  $st->bind_param('i', $id); $st->execute(); $st->close();
}
