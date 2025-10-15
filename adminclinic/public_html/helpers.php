<?php
declare(strict_types=1);
/**
 * helpers.php – common utility helpers shared by admin-clinic and tenant scripts
 */

require_once __DIR__ . '/sql_functions.php';   // gives us run() / db()

/* ──────────────────────────────────────────────────────────────────────
 *  TABLE-INTROSPECTION HELPERS (used by scaffolded CREATE/UPDATE pages)
 * ─────────────────────────────────────────────────────────────────── */

/**
 * Build an associative array of column => sanitized value suitable
 * for INSERT or UPDATE.  Uses INFORMATION_SCHEMA so it adapts to any table.
 */
function parse_columns(string $table_name, array $postdata): array
{
    $cols = run("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
          FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_NAME = ?", [$table_name]);

    $vars = [];

    foreach ($cols as $c) {
        $col   = $c['COLUMN_NAME'];
        $type  = $c['DATA_TYPE'];
        $val   = $postdata[$col] ?? '';          // raw user-input or empty string
        $final = $val;                           // will be normalised below

        /* Normalise empty or special-marker values per type */
        if ($val === '') {
            switch ($type) {
                case 'decimal':
                case 'tinyint':
                    $final = 0;
                    break;

                case 'datetime':
                    // allow NULL if column nullable, else current timestamp
                    $final = ($c['IS_NULLABLE'] === 'YES') ? null : date('Y-m-d H:i:s');
                    break;

                default:
                    $final = null;   // safe default
            }
        } elseif ($val === 'CURRENT_TIMESTAMP' && $type === 'datetime') {
            $final = date('Y-m-d H:i:s');
        }

        $vars[$col] = is_string($final) ? trim($final) : $final;
    }
    return $vars;
}

/**
 * Fetch COLUMN_DEFAULT and COLUMN_COMMENT for one column.
 */
function get_column_attributes(string $table, string $column): ?array
{
    $row = run("
        SELECT COLUMN_DEFAULT, COLUMN_COMMENT
          FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_NAME = ? AND COLUMN_NAME = ?", [$table, $column]);

    return $row[0] ?? null;
}

/* ──────────────────────────────────────────────────────────────────────
 *  GENERIC SMALL HELPERS
 * ─────────────────────────────────────────────────────────────────── */

function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * Quick Yes/No/Em-dash helper for tinyint(1) fields.
 */
function yn($v, string $yes = 'Yes', string $no = 'No', string $dash = '—'): string
{
    if ($v === null || $v === '') return $dash;
    return ((int)$v) === 1 ? $yes : $no;
}

/** Non-strict count of a value inside an array */
function arrayCount($value, array $array): int
{
    $n = 0;
    foreach ($array as $item) {
        if ($value == $item) $n++;
    }
    return $n;
}

/** Truncate long strings at a word boundary */
function truncate(string $str, int $len = 100, string $append = '&hellip;'): string
{
    $str = trim($str);
    if (mb_strlen($str) > $len) {
        $str = wordwrap($str, $len, "\n", true);
        $str = explode("\n", $str, 2)[0] . $append;
    }
    return $str;
}

/** GET/POST helper with fallback default */
function getParam(string $key, $default = null)
{
    return isset($_GET[$key])  ? trim($_GET[$key])  :
           (isset($_POST[$key]) ? trim($_POST[$key]) : $default);
}

/** Normalise phone number into 123-456-7890 or 123-4567 */
function formatPhone(string $input): string
{
    $digits = preg_replace('/\D/', '', $input);

    if (strlen($digits) === 7) {
        return preg_replace('/([0-9]{3})([0-9]{4})/', '$1-$2', $digits);
    }
    if (strlen($digits) === 10) {
        return preg_replace('/([0-9]{3})([0-9]{3})([0-9]{4})/', '$1-$2-$3', $digits);
    }
    return $input;   // fall-back to original string
}
?>
