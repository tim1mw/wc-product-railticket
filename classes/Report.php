<?php
namespace wc_railticket;
defined('ABSPATH') or die('No script kiddies please!');

class Report {

    protected function report_row($rows, $type = 'td', $cola = 'th') {
        echo "<tr>";
        $first = true;
        foreach ($rows as $row) {
            if ($first) {
                echo "<" . $cola . ">" . $row . "</" . $cola . ">";
                $first = false;
            } else {
                echo "<" . $type . ">" . $row . "</" . $type . ">";
            }
        }
        echo "</tr>";
    }

}
