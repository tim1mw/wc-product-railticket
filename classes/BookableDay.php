<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class BookableDay {
    private $data;
    public $timetable;

    private function __construct($data) {
        $this->timetable = Timetable::get_timetable($data->timetableid, $data->ttrevision);
        $this->data = $data;
        $this->data->composition = json_decode($data->composition);
        $this->data->reserve = json_decode($data->reserve);
    }

    public static function get_bookable_day($dateofjourney, $usedateid = false) {
        global $wpdb;

        $sql = "SELECT * FROM {$wpdb->prefix}wc_railticket_bookable bookable ";

        if ($usedateid) {
            $sql .= "INNER JOIN {$wpdb->prefix}wc_railticket_dates dates ON bookable.date = dates.date ".
                "WHERE dates.id = ".$dateofjourney;
        } else {
            $sql .= "WHERE bookable.date = '".$dateofjourney."'";
        }

        $bd = $wpdb->get_row($sql, OBJECT);

        if ($bd) {
            return new BookableDay($bd);
        }

        return false;
    }

    public function get_override() {
        return $this->data->override;
    }

    public function get_all_bookings() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE date = '".$this->data->date."'");
    }
}
