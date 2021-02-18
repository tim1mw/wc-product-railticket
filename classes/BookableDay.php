<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class BookableDay {
    private $data;
    public $timetable;

    private function __construct($data) {
        $this->timetable = Timetable::get_timetable($data->timetableid, $data->ttrevision);
        $this->data = $data;

        // Pre-process json stuff that gets used a lot.
        if (strlen($this->data->reserve) > 0) {
            $this->hasreserve = true;
        } else {
            $this->hasreserve = false;
        }
        $this->data->reserve = json_decode($this->data->reserve);
        $this->data->bays = json_decode($this->data->bays);
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

    public function sold_out() {
        if ($this->data->soldout == 1) {
            return true;
        }
        return false;
    }

    public function is_bookable() {
        if ($this->data->bookable == 1) {
            return true;
        }
        return false;
    }

    public function get_date() {
        return $this->data->date;
    }

    public function get_override() {
        return $this->data->override;
    }

    public function get_daytype() {
        return $this->data->daytype;
    }

    public function get_bays() {
        return $this->data->bays;
    }

    public function get_reserve() {
        return $this->data->reserve;
    }

    public function has_reserve() {
        return $this->hasreserve;
    }

    public function sell_reserve() {
        if ($this->data->sellreserve == 1) {
            return true;
        }

        return false;
    }

    public function get_composition() {
        // Do this on the fly because it isn't used that much
        return json_decode($this->data->composition);
    }

    public function get_all_bookings() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE date = '".$this->data->date."'");
    }

    public function get_bookings_from_station(Station $station, $deptime, $direction) {
        global $wpdb;

        return $wpdb->get_results("SELECT bookings.*, stations.name ".
        "FROM {$wpdb->prefix}wc_railticket_bookings bookings ".
        "LEFT JOIN {$wpdb->prefix}wc_railticket_stations stations ON ".
        "    stations.stnid = bookings.tostation AND stations.revision = ".$station->get_revision()." ".
        "WHERE bookings.date='".$this->data->date."' AND ".
        "bookings.time = '".$deptime."' AND bookings.fromstation = ".$station->get_stnid()." AND bookings.direction = '".$direction."' ");
    }

    public function get_price_revision() {
        return $this->data->pricerevision;
    }
}
