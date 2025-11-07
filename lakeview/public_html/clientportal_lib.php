<?php
declare(strict_types=1);

/**
 * Lakeview — clientportal_lib.php
 * --------------------------------
 * Purpose
 * - Resolve join links (Zoom) by therapy_group override, else by program default.
 * - Resolve payment links per program (Parenting has a direct Square link; others use Payment Options).
 * - Provide {{group_link}} for reminder templates via notesao_regular_group_link().
 * - NEW: Support programs whose dates "vary" via clientportal_group_dates table.
 *
 * Assumptions from Lakeview materials:
 * Program → Zoom
 *   1 DOEP                      → https://us02web.zoom.us/j/81309717789
 *   2 DWIE                      → https://us02web.zoom.us/j/87628712130
 *   3 DWII                      → https://us02web.zoom.us/j/82208338625
 *   4 Parenting Education       → https://us02web.zoom.us/j/89109797950
 *   5 Thinking for a Change     → https://us02web.zoom.us/j/85959298570
 *   6 Life Skills / Anti-Theft  → https://us02web.zoom.us/j/84878523499
 *   9 Marijuana Education       → https://us02web.zoom.us/j/87320225450
 *  10 Marijuana Intervention    → https://us02web.zoom.us/j/87320225450  (same as above)
 *  11 SAE                       → facilitator provides link (no static Zoom listed)
 *  12 Anger Management          → https://us02web.zoom.us/j/82611581194
 *
 * Payments:
 *  - Parenting Education has a direct Square checkout link ($75) from the doc.
 *  - All other programs route to the clinic Payment Options page.
 *  - CPS cases are “No charge” (UI handles hiding button).
 *
 * Variable-date programs:
 *  - Use clientportal_group_dates to publish upcoming one-off dates (optionally scoped to a therapy_group).
 *  - Helpers here fetch and format the next/next-few dates for display in the portal or reminders.
 */

// ---------------------------------------------------------------------
// Generic SQL helpers
// ---------------------------------------------------------------------
if (!function_exists('sql_select_one')) {
    function sql_select_one(mysqli $con, string $sql, array $params = []): ?array {
        $stmt = $con->prepare($sql);
        if (!$stmt) return null;
        if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('sql_select_all')) {
    function sql_select_all(mysqli $con, string $sql, array $params = []): array {
        $stmt = $con->prepare($sql);
        if (!$stmt) return [];
        if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }
}

/**
 * Safe column existence check for optional fields.
 */
if (!function_exists('column_exists')) {
    function column_exists(mysqli $con, string $table, string $column): bool {
        // basic identifier whitelist
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) return false;
        $tableEsc = $con->real_escape_string($table);
        $colEsc   = $con->real_escape_string($column);
        $sql = "SHOW COLUMNS FROM `$tableEsc` LIKE '$colEsc'";
        if ($res = $con->query($sql)) {
            $ok = $res->num_rows > 0;
            $res->free();
            return $ok;
        }
        return false;
    }
}

// ---------------------------------------------------------------------
// Basic portal helpers
// ---------------------------------------------------------------------
function clientportal_default_url(): string {
    return '/clientportal.php';
}

// ---------------------------------------------------------------------
// Join-link mapping (Zoom)
// ---------------------------------------------------------------------

// TG-specific overrides (leave empty unless a particular TG differs from program default)
function join_links_by_therapy_group(): array {
    return [
        // Example:
        // 123 => 'https://us02web.zoom.us/j/XXXXXXXXXXX',
    ];
}

// Program defaults (source: Lakeview doc)
function join_links_by_program(): array {
    return [
         1  => 'https://us02web.zoom.us/j/81309717789', // DOEP
         2  => 'https://us02web.zoom.us/j/87628712130', // DWIE
         3  => 'https://us02web.zoom.us/j/82208338625', // DWII
         4  => 'https://us02web.zoom.us/j/89109797950', // Parenting
         5  => 'https://us02web.zoom.us/j/85959298570', // Thinking for a Change
         6  => 'https://us02web.zoom.us/j/84878523499', // Life Skills / Anti-Theft
         7  => 'https://us02web.zoom.us/j/86047659700', // BIPP (male)
         8  => 'https://us02web.zoom.us/j/83137357345', // BIPP (female)
         9  => 'https://us02web.zoom.us/j/87320225450', // Marijuana Education
        10  => 'https://us02web.zoom.us/j/87320225450', // Marijuana Intervention
        // 11 SAE intentionally omitted → no static Zoom provided
        12  => 'https://us02web.zoom.us/j/82611581194', // Anger Management
    ];
}


/**
 * Resolve the join link for a given TG and Program.
 * Order: TG override → Program default → empty string.
 */
function resolve_join_link_for(int $therapy_group_id, int $program_id): string {
    $tgMap = join_links_by_therapy_group();
    if ($therapy_group_id > 0 && isset($tgMap[$therapy_group_id])) {
        return (string)$tgMap[$therapy_group_id];
    }
    $progMap = join_links_by_program();
    return $progMap[$program_id] ?? '';
}

// ---------------------------------------------------------------------
// Payment links + optional payment status helper
// ---------------------------------------------------------------------
function payment_options_url(): string {
    return 'https://lakevieweducation.com/payment-options';
}

// Parenting explicit Square link from document
function parenting_square_pay_url(): string {
    return 'https://checkout.square.site/pay/eb1333e9e236459b9d893006a95c046d';
}

/**
 * Resolve payment link for a client row (expects at least program_id).
 * - Parenting (program_id=4) uses the Square link.
 * - All others use the Payment Options page.
 * UI layer should suppress the button for CPS “No charge”.
 *
 * NOTE: DO NOT reference non-existent columns here to avoid fatals.
 */
function portal_payment_link_for_client(array $client): string {
    $pid = (int)($client['program_id'] ?? 0);
    if ($pid === 4) return parenting_square_pay_url(); // Parenting Education
    return payment_options_url();
}

/**
 * Optional payment status exposer for the portal UI.
 * Returns an array like:
 *   [
 *     'known'        => true|false,   // did we find columns / data?
 *     'status'       => 'paid'|'partial'|'unpaid'|'unknown',
 *     'detail'       => 'Payment received ($75 via Square)' | '—',
 *     'paid_amount'  => float|null,
 *     'paid_source'  => string|null,
 *   ]
 *
 * This method checks for existence of client.paid_amount / client.paid_source first.
 * If they do not exist, it returns ['known' => false, 'status' => 'unknown', ...]
 *
 * You can ignore this helper if you’re not ready to surface status.
 */
function client_payment_status_for_portal(mysqli $con, array $client): array {
    $rowId = (int)($client['id'] ?? 0);
    if ($rowId <= 0) {
        return ['known'=>false,'status'=>'unknown','detail'=>'—','paid_amount'=>null,'paid_source'=>null];
    }

    $hasPaidAmount = column_exists($con, 'client', 'paid_amount');
    $hasPaidSource = column_exists($con, 'client', 'paid_source');

    if (!$hasPaidAmount && !$hasPaidSource) {
        return ['known'=>false,'status'=>'unknown','detail'=>'—','paid_amount'=>null,'paid_source'=>null];
    }

    $cols = ['id'];
    if ($hasPaidAmount) $cols[] = 'paid_amount';
    if ($hasPaidSource) $cols[] = 'paid_source';
    $sql  = "SELECT ".implode(',', $cols)." FROM client WHERE id = ? LIMIT 1";
    $rec  = sql_select_one($con, $sql, [(string)$rowId]) ?? [];

    $amt = isset($rec['paid_amount']) ? (float)$rec['paid_amount'] : null;
    $src = isset($rec['paid_source']) ? (string)$rec['paid_source'] : null;

    if ($amt !== null && $amt > 0) {
        // Heuristic: treat >= 75 as “paid” for Parenting single fee; tune as needed per program.
        $status = 'partial';
        if ((int)($client['program_id'] ?? 0) === 4 && $amt >= 75) {
            $status = 'paid';
        }
        $detail = 'Payment received ($'.number_format((float)$amt, 2).($src ? ' via '.$src : '').')';
        return ['known'=>true,'status'=>$status,'detail'=>$detail,'paid_amount'=>$amt,'paid_source'=>$src];
    }

    return ['known'=>true,'status'=>'unpaid','detail'=>'No payment on file','paid_amount'=>null,'paid_source'=>null];
}

// ---------------------------------------------------------------------
// Variable-date programs — clientportal_group_dates
// ---------------------------------------------------------------------

/**
 * Return upcoming one-off dates for a program (optionally scoped to a TG).
 * If $therapy_group_id is provided, results include BOTH TG matches and program-level (NULL TG),
 * with TG-specific rows preferred first.
 *
 * @return array<array{ id:int, program_id:int, therapy_group_id:?int, starts_at:string, note:?string }>
 */
function get_upcoming_group_dates(mysqli $con, int $program_id, ?int $therapy_group_id = null, int $limit = 5): array {
    $limit = max(1, min(50, $limit)); // clamp
    if ($therapy_group_id !== null) {
        // TG-specific first, then program-level (NULL)
        $sql = "
            SELECT gd.*
            FROM clientportal_group_dates gd
            WHERE gd.program_id = ?
              AND gd.starts_at >= NOW()
              AND (gd.therapy_group_id = ? OR gd.therapy_group_id IS NULL)
            ORDER BY (gd.therapy_group_id IS NULL) ASC, gd.starts_at ASC
            LIMIT $limit
        ";
        return sql_select_all($con, $sql, [(string)$program_id, (string)$therapy_group_id]);
    } else {
        // Only program-level rows
        $sql = "
            SELECT gd.*
            FROM clientportal_group_dates gd
            WHERE gd.program_id = ?
              AND gd.therapy_group_id IS NULL
              AND gd.starts_at >= NOW()
            ORDER BY gd.starts_at ASC
            LIMIT $limit
        ";
        return sql_select_all($con, $sql, [(string)$program_id]);
    }
}

/**
 * Quick check: does this program have any upcoming variable dates?
 */
function program_has_upcoming_variable_dates(mysqli $con, int $program_id, ?int $therapy_group_id = null): bool {
    $rows = get_upcoming_group_dates($con, $program_id, $therapy_group_id, 1);
    return !empty($rows);
}

/**
 * Format a DATETIME for client-facing display.
 */
function format_portal_datetime(string $mysqlDateTime): string {
    $ts = strtotime($mysqlDateTime);
    return $ts ? date('l, F j, Y g:i A', $ts) : $mysqlDateTime;
}

/**
 * Find the very next upcoming date (if any) for a client’s program/group.
 * Returns: ['id'=>int,'starts_at'=>string,'note'=>?string] or null.
 */
function next_group_date_for_client(mysqli $con, array $client): ?array {
    $pid  = (int)($client['program_id'] ?? 0);
    $tgid = isset($client['therapy_group_id']) ? (int)$client['therapy_group_id'] : null;
    if ($pid <= 0) return null;

    $rows = get_upcoming_group_dates($con, $pid, $tgid, 1);
    if (!$rows) return null;

    $row = $rows[0];
    return [
        'id'        => (int)$row['id'],
        'starts_at' => $row['starts_at'],
        'note'      => $row['note'] ?? null,
    ];
}

/**
 * For reminder scripts: return up to $limit upcoming starts_at values (ISO strings)
 * scoped to a particular client’s program/group.
 */
function upcoming_group_datetimes_for_client(mysqli $con, int $clientId, int $limit = 3): array {
    $cli = sql_select_one(
        $con,
        "SELECT program_id, therapy_group_id FROM client WHERE id = ? LIMIT 1",
        [(string)$clientId]
    );
    if (!$cli) return [];

    $pid  = (int)$cli['program_id'];
    $tgid = isset($cli['therapy_group_id']) ? (int)$cli['therapy_group_id'] : null;

    $rows = get_upcoming_group_dates($con, $pid, $tgid, $limit);
    return array_map(fn($r) => (string)$r['starts_at'], $rows);
}

// ---------------------------------------------------------------------
// Weekly schedule mapping (static) — for programs that DO have fixed weekly times
// Leave entries empty for “dates vary” programs; UI should switch to variable dates.
// ---------------------------------------------------------------------
function weekly_slots_by_program(): array {
    return [
        // Examples if you decide to display weekly fixed slots:
        // 5  => [ 'Mon 7:00 PM (Virtual)', 'Wed 7:00 PM (Virtual)' ], // Thinking for a Change
        // 12 => [ 'Thu 6:00 PM (Virtual)' ],                         // Anger Management
        // Programs like DOEP (1), DWIE (2), DWII (3) → leave empty so variable dates show instead.
    ];
}

/**
 * Convenience: Get weekly slots text array for a given program_id.
 */
function get_weekly_slots_for_program(int $program_id): array {
    $map = weekly_slots_by_program();
    return $map[$program_id] ?? [];
}

// ---------------------------------------------------------------------
// Reminder template helper: {{group_link}}
// ---------------------------------------------------------------------
/**
 * Returns the best URL for {{group_link}} in reminder emails:
 * - If the client’s therapy_group appears to be virtual (address == 'Virtual')
 *   and we can resolve a join link, return that join link.
 * - Otherwise, return the portal URL.
 *
 * Variable-dates control *dates*, not links. Links still come from TG override / program default.
 */
function notesao_regular_group_link(mysqli $con, int $clientId): string {
    $client = sql_select_one(
        $con,
        "SELECT therapy_group_id, program_id FROM client WHERE id = ? LIMIT 1",
        [(string)$clientId]
    );
    if (!$client) return clientportal_default_url();

    $tgId = (int)($client['therapy_group_id'] ?? 0);
    $pid  = (int)($client['program_id'] ?? 0);

    // Read TG meta to determine virtual vs in-person
    $tg = null;
    if ($tgId > 0) {
        $tg = sql_select_one(
            $con,
            "SELECT address FROM therapy_group WHERE id = ? LIMIT 1",
            [(string)$tgId]
        );
    }
    $address   = (string)($tg['address'] ?? '');
    $isVirtual = strcasecmp($address, 'Virtual') === 0;

    if ($isVirtual) {
        $join = resolve_join_link_for($tgId, $pid);
        if ($join !== '') return $join;
    }

    return clientportal_default_url();
}
