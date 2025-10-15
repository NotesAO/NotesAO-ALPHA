<?php
// -----------------------------------------------------------------------------
// clientportal_lib.php  (LIBRARY ONLY)
// -----------------------------------------------------------------------------
// PURPOSE:
//   Given a client id, return the "regular group" URL exactly like the client
//   portal would. NO output, NO session_start, NO constant definitions, NO
//   DB connect/close. Uses the provided $con (mysqli) from the caller.
//
// HOW TO USE:
//   1) Paste your existing $groupData mapping into the section below.
//      Keep each entry structure like:
//         [
//           'program_id'        => 2,
//           'referral_type_id'  => 2,
//           'gender_id'         => 2,           // 1=male, 2=female, 3=any (example)
//           'required_sessions' => 18,
//           'fee'               => 15,
//           'therapy_group_id'  => 106,         // exact DB group id (if applicable)
//           'label'             => "Saturday Men's Parole/CPS 18 Week (9AM)",
//           'day_time'          => "Saturday 9AM",
//           'link'              => "https://..."
//         ]
//   2) Include this file from pages that need {{group_link}}:
//          require_once 'clientportal_lib.php';
//   3) The reminders page will call:
//          notesao_regular_group_link($con, $clientId)
// -----------------------------------------------------------------------------

declare(strict_types=1);

/* ============================================================================
 * 1) GROUP DATA (PASTE YOUR MAPPING HERE)
 * ========================================================================== */
$groupData = [
    // ===================== SATURDAY 9AM Men’s Virtual BIPP (id=106) =====================
    
    

];

/* ============================================================================
 * 2) HELPERS (pure functions; no side effects)
 * ========================================================================== */

/**
 * Default client portal URL when no mapping can be resolved.
 */
function clientportal_default_url(): string {
    $base = defined('APP_BASE_URL') ? APP_BASE_URL : 'https://lakeview.notesao.com';
    return rtrim($base, '/') . '/client.php';
}

/**
 * Some portals normalize referral types (e.g., aliases collapse to a canonical id).
 * If you have a specific mapping in your original portal, apply it here.
 * Current default: identity.
 */
function normalizeReferralId(int $refId): int {
    // TODO: If your portal maps multiple IDs to a canonical value, apply here.
    // Example:
    // $map = [ 10 => 2, 11 => 2, 12 => 2 ]; // map 10/11/12 to 2
    // return $map[$refId] ?? $refId;
    return $refId;
}

/**
 * Gender compatibility:
 *   - 3 in groupData = "any"
 *   - otherwise must equal client's gender
 */
function gender_matches(int $groupGender, int $clientGender): bool {
    return ($groupGender === 3) || ($groupGender === $clientGender);
}

/**
 * Compute a score for how well a groupData row matches the client profile.
 * Higher score = better match.
 */
function score_group_candidate(array $g, array $c): int {
    $score = 0;
    // Hard filter already: program & gender
    // Strong match on exact therapy_group_id
    if ((int)$g['therapy_group_id'] === (int)$c['therapy_group_id']) $score += 8;
    // Referral type match (after normalization)
    if ((int)$g['referral_type_id'] === normalizeReferralId((int)$c['referral_type_id'])) $score += 4;
    // Required sessions match
    if ((int)$g['required_sessions'] === (int)$c['required_sessions']) $score += 2;
    // Fee match
    if ((int)$g['fee'] === (int)$c['fee']) $score += 1;

    return $score;
}

/**
 * Tie-break: prefer candidates with therapy_group_id exact match, then lower fee,
 * then first encountered (stable).
 */
function better_than(array $a, array $b, array $client): bool {
    if ($a['score'] !== $b['score']) return $a['score'] > $b['score'];

    $tgA = (int)$a['row']['therapy_group_id'];
    $tgB = (int)$b['row']['therapy_group_id'];
    $want = (int)$client['therapy_group_id'];

    $aExact = ($tgA === $want);
    $bExact = ($tgB === $want);
    if ($aExact !== $bExact) return $aExact; // exact wins

    // Prefer lower fee
    $feeA = (int)$a['row']['fee'];
    $feeB = (int)$b['row']['fee'];
    if ($feeA !== $feeB) return $feeA < $feeB;

    // Otherwise keep existing winner (stable)
    return false;
}

/* ============================================================================
 * 3) PUBLIC API
 * ========================================================================== */

/**
 * Return the client's regular group URL using $groupData mapping.
 *
 * - Pull minimal client profile (single query).
 * - Filter candidates by program_id and gender (3 = any).
 * - Score candidates by therapy_group, referral_type, required_sessions, fee.
 * - Return best link; fallback to portal if none.
 */
function notesao_regular_group_link(mysqli $con, int $clientId): string {
    // Fetch minimal profile for matching
    $stmt = $con->prepare("
        SELECT id, program_id, referral_type_id, gender_id, required_sessions, fee, therapy_group_id
        FROM client
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        // Can't prepare => safe fallback
        return clientportal_default_url();
    }
    $stmt->bind_param('i', $clientId);
    $stmt->execute();
    $res = $stmt->get_result();
    $client = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$client) {
        return clientportal_default_url();
    }

    // Ensure ints
    $client['program_id']        = (int)$client['program_id'];
    $client['referral_type_id']  = normalizeReferralId((int)$client['referral_type_id']);
    $client['gender_id']         = (int)$client['gender_id'];
    $client['required_sessions'] = (int)$client['required_sessions'];
    $client['fee']               = (int)$client['fee'];
    $client['therapy_group_id']  = (int)$client['therapy_group_id'];

    // Use the global mapping (paste above). If empty, fallback.
    global $groupData;
    if (empty($groupData) || !is_array($groupData)) {
        return clientportal_default_url();
    }

    // Filter & score candidates
    $best = null; // ['row' => array, 'score' => int]
    foreach ($groupData as $row) {
        // Skip incomplete rows
        if (!isset(
            $row['program_id'], $row['referral_type_id'], $row['gender_id'],
            $row['required_sessions'], $row['fee'],
            $row['therapy_group_id'], $row['link']
        )) {
            continue;
        }

        // Program must match
        if ((int)$row['program_id'] !== $client['program_id']) continue;

        // Gender check (3 = any)
        if (!gender_matches((int)$row['gender_id'], $client['gender_id'])) continue;

        // Score
        $score = score_group_candidate($row, $client);
        $candidate = ['row' => $row, 'score' => $score];

        if ($best === null || better_than($candidate, $best, $client)) {
            $best = $candidate;
        }
    }

    if ($best && !empty($best['row']['link'])) {
        return (string)$best['row']['link'];
    }

    return clientportal_default_url();
}
