<?php
// retrieves and enhances postdata table keys and values on CREATE and UPDATE events
function parse_columns($table_name, $postdata)
{
    global $link;
    $vars = array();

    // prepare a default return value
    $default = null;

    // get all columns, including the ones not sent by the CRUD form
    $sql = "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE table_name = '" . $table_name . "'";
    $result = mysqli_query($link, $sql);
    while ($row = mysqli_fetch_assoc($result)) {

        $debug = 0;
        if ($debug) {
            echo "<pre>";
            // print_r($postdata);
            echo $row['COLUMN_NAME'] . "\t";
            echo $row['DATA_TYPE'] . "\t";
            echo $row['IS_NULLABLE'] . "\t";
            echo $row['COLUMN_DEFAULT'] . "\t";
            echo $row['EXTRA'] . "\t";
            echo $default . "\n";
            echo "</pre>";
        }

        switch ($row['DATA_TYPE']) {

                // fix "Incorrect decimal value: '' error in STRICT_MODE or STRICT_TRANS_TABLE
                // @see https://dev.mysql.com/doc/refman/5.7/en/sql-mode.html
            case 'decimal':
                $default = 0;
                break;

            case 'tinyint':
                $default = 0;
                break;

                // fix "Incorrect datetime value: '0' " on non-null datetime columns
                // with 'CURRENT_TIMESTAMP' default not being set automatically
                // and refusing to take NULL value
            case 'datetime':
                if ($row['COLUMN_DEFAULT'] != 'CURRENT_TIMESTAMP' && $row['IS_NULLABLE'] == 'YES') {
                    $default = null;
                } else {
                    $default =  date('Y-m-d H:i:s');
                }
                if ($postdata[$row['COLUMN_NAME']] == 'CURRENT_TIMESTAMP') {
                    $_POST[$row['COLUMN_NAME']] = date('Y-m-d H:i:s');
                }
                break;
        }

        // check that fieldname was set before sending values to pdo
        $vars[$row['COLUMN_NAME']] = isset($_POST[$row['COLUMN_NAME']]) && $_POST[$row['COLUMN_NAME']] ? trim($_POST[$row['COLUMN_NAME']]) : $default;
    }
    return $vars;
}



// get extra attributes for  table keys on CREATE and UPDATE events
function get_columns_attributes($table_name, $column)
{
    global $link;
    $sql = "SELECT COLUMN_DEFAULT, COLUMN_COMMENT
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE table_name = '" . $table_name . "'
            AND column_name = '" . $column . "'";
    $result = mysqli_query($link, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $debug = 0;
        if ($debug) {
            echo "<pre>";
            print_r($row);
            echo "</pre>";
        }
        return $row;
    }
}

function arrayCount($value, $array)
{
    $count = 0;
    foreach ($array as $item) {
        if ($value == $item) {
            $count++;
        }
    }
    return $count;
}

function truncate($string, $length = 100, $append = "&hellip;")
{
    $string = trim($string);

    if (strlen($string) > $length) {
        $string = wordwrap($string, $length);
        $string = explode("\n", $string, 2);
        $string = $string[0] . $append;
    }

    return $string;
}

function getParam($key, $default = null)
{
    $value = $default;
    if (isset($_GET[$key])) {
        $value = trim($_GET[$key]);
    }
    if (isset($_POST[$key])) {
        $value = trim($_POST[$key]);
    }
    return $value;
}

function formatPhone($inputPhone)
{
    // Start By Scrubbing Everything Except Numbers
    $inputPhone = preg_replace("/[^0-9]/", "", $inputPhone);

    // If it is a 7 digit number
    if (strlen($inputPhone) == 7)
        $inputPhone = preg_replace("/([0-9]{3})([0-9]{4})/", "$1-$2", $inputPhone);

    // If it is a 10 digit number
    if (strlen($inputPhone) == 10)
        $inputPhone = preg_replace("/([0-9]{3})([0-9]{3})([0-9]{4})/", "$1-$2-$3", $inputPhone);

    return $inputPhone;
}
