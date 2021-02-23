<?php

namespace wc_railticket;
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class BookableDay {
    private $data;
    public $timetable, $fares;

    private function __construct($data, $timetable = false, $fares = false) {
        if (!$timetable) {
            $this->timetable = Timetable::get_timetable($data->timetableid, $data->ttrevision, $data->date);
        } else {
            $this->timetable = $timetable;
        }
        if (!$fares) {
            $this->fares = FareCalculator::get_fares($data->pricerevision);
        } else {
            $this->fares = $fares;
        }
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

    public static function get_next_bookable_dates($date, $num) {
        global $wpdb;
        if ($date instanceof DateTime) {
            $date = $date->format('Y-m-d');
        }

        $sql = "SELECT date FROM {$wpdb->prefix}wc_railticket_bookable ".
            "WHERE date >= '".$date."' AND bookable = 1 AND soldout = 0 ORDER BY date ASC LIMIT ".$num;


        $rec = $wpdb->get_results($sql);

        if (count($rec) > 0) {
            return $rec;
        } else {
            return false;
        }
    }

    public static function is_date_bookable(\DateTime $date, $skiptime = false) {
        global $wpdb;

        $today = new \DateTime();
        $nowm = (intval($today->format('H')) * 60) + intval($today->format('i'));
        $today->setTime(0,0,0);
        if ($date < $today) {
            return false;
        }

        if (!$skiptime && $date == $today) {
            // Check the last train hasn't departed
            $lt = $timetable->get_last_train();
            $endtime = (intval($lt->hour)*60) + intval($lt->min) + intval(get_option("wc_product_railticket_bookinggrace"));
            if ($nowm > $endtime) {
               // return false;
            }
        }

        $data = $wpdb->get_row("SELECT id FROM {$wpdb->prefix}wc_railticket_bookable ".
            "WHERE date = '".$date->format('Y-m-d')."' AND bookable = 1 AND soldout = 0 ");

        if ($data) {
            return true;
        }

        return false;
    }

    public static function is_date_sold_out(\DateTime $date) {
        global $wpdb;

        $data = $wpdb->get_row("SELECT id FROM {$wpdb->prefix}wc_railticket_bookable ".
            "WHERE date = '".$date->format('Y-m-d')."' AND soldout = 1 ");

        if ($data) {
            return true;
        }

        return false;
    }

    public static function date_has_specials($date) {
        global $wpdb;

        $data = $wpdb->get_row("SELECT id FROM {$wpdb->prefix}wc_railticket_specials ".
            "WHERE date = '".$date."'");

        if ($data) {
            return true;
        }

        return false;
    }

    public static function create_bookable_day($dateofjourney) {
        $timetable = Timetable::get_timetable_by_date($dateofjourney);
        $fares = FareCalculator::get_fares_by_date($dateofjourney);

        $ssr = false;
        if (get_option('wc_product_railticket_sameservicereturn') == 'on') {
            $ssr = true;
        }
        $coaches = CoachManager::process_coaches(json_decode(get_option('wc_product_railticket_defaultcoaches')), $timetable);
        $data = new \stdclass();
        $data->date = $dateofjourney;
        $data->daytype = $coaches->daytype;
        $data->allocateby = $coaches->allocateby;
        $data->composition = json_encode($coaches->coachset);
        $data->bays = json_encode($coaches->bays);
        $data->bookclose = '{}';
        $data->limits = get_option('wc_product_railticket_bookinglimits');
        $data->bookable = 0;
        $data->soldout = 0;
        $data->override = self::randomString();
        $data->sameservicereturn = $ssr;
        $data->reserve = json_encode($coaches->reserve);
        $data->sellreserve = 0;
        $data->specialonly = 0;
        $data->ttrevision = $timetable->get_revision();
        $data->timetableid = $timetable->get_timetableid();
        $data->pricerevision = $fares->get_revision();
        $data->id = -1;

        return new BookableDay($data, $timetable, $fares);
    }

    private static function randomString() {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $randstring = '';
        for ($i = 0; $i < 6; $i++) {
            $randstring .= $characters[rand(0, strlen($characters)-1)];
        }
        return $randstring;
    }


    public function update_bookable($ndata) {
        global $wpdb;
        $filtered = array();

        foreach($ndata as $key => $value) {

            switch ($key) {
                case 'composition':
                    // This should always be coming from the coach set manager wigit as JSON, so decode
                    $jdata = json_decode($ndata->composition);

                    $coaches = CoachManager::process_coaches($jdata);
                    $filtered['composition'] = $ndata->composition;
                    $filtered['daytype'] = $coaches->daytype;
                    $filtered['allocateby'] = $coaches->allocateby;
                    $filtered['reserve'] = json_encode($coaches->reserve);
                    $filtered['bays'] = json_encode($coaches->bays);

                    $this->data->composition = $jdata;
                    $this->data->daytype = $coaches->daytype;
                    $this->data->allocateby = $coaches->allocateby;
                    $this->data->reserve = $coaches->reserve;
                    $this->data->bays = $coaches->bays;
                    break;
                case 'daytype':
                case 'allocateby':
                case 'reserve':
                case 'hasreserve':
                case 'bays':
                    throw new TicketException("Coach set configuration must be set via the composition property only");
                    break;
                default:
                    if (!property_exists($this->data, $key)) {
                        throw new TicketException("Invalid property in data: ".$key);
                    }
                    $filtered[$key] = $value;
                    $this->data->$key = $value;
                    break;
            }
        }
        if ($this->data->id == -1) {
            // We need to fill in any missing properties in filtered from data
            foreach ((array) $this->data as $key => $value) {
                if ($key == 'id') {continue;}

                if (!array_key_exists($key, $filtered)) {
                    $filtered[$key] = $value;
                }
            }

            $wpdb->insert("{$wpdb->prefix}wc_railticket_bookable", $filtered);
            $this->id = $wpdb->insert_id;
        } else {
            $wpdb->update("{$wpdb->prefix}wc_railticket_bookable", $filtered, array('id' => $this->data->id));
        }
    }

    public function sold_out() {
        if ($this->data->soldout == 1) {
            return true;
        }
        return false;
    }

    public function special_only() {
        if ($this->data->specialonly == 1) {
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

    public function same_service_return() {
        if ($this->data->sameservicereturn == 1) {
            return true;
        }

        return false;
    }

    public function get_date($format = false) {
        if (!$format) {
            return $this->data->date;
        }

        $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
        $jdate = \DateTime::createFromFormat('Y-m-d', $this->data->date, $railticket_timezone);
        return strftime(get_option('wc_railticket_date_format'), $jdate->getTimeStamp());
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

    public function get_reserve($format = false) {
        if (!$format) {
            return $this->data->reserve;
        }

        return CoachManager::format_reserve($this->data->reserve, $this->data->daytype);
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

    public function get_composition($format = false) {
        // Do this on the fly because it isn't used that much
        if (!$format) {
            return json_decode($this->data->composition);
        }

        return CoachManager::format_composition(json_decode($this->data->composition), $this->data->daytype);
    }

    public function get_all_bookings() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE date = '".$this->data->date."'");
    }

    public function count_bookings() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}wc_railticket_bookings WHERE date = '".$this->data->date."'");
    }

    public function get_bookings_from_station(Station $station, $deptime, $direction) {
        global $wpdb;

        $bks = $wpdb->get_results("SELECT bookings.* ".
            "FROM {$wpdb->prefix}wc_railticket_bookings bookings ".
            "WHERE bookings.date='".$this->data->date."' AND ".
            "bookings.time = '".$deptime."' AND bookings.fromstation = ".$station->get_stnid()." AND bookings.direction = '".$direction."' ");

        $bookings = array();
        foreach ($bks as $bk) {
            $bookings[] = new Booking($bk, $this);
        }

        return $bookings;
    }

    public function get_price_revision() {
        return $this->data->pricerevision;
    }

    public function get_data() {
        return $this->data;
    }

    public function get_specials($dataonly = false) {
        return Special::get_specials($this->data->date, $dataonly);
    }

    public function get_booking_limits() {

        /*
        $bookable = array();
        $bookable['from'] = array();
        $bookable['to'] = array();
        $bookable['override'] = $bkrec->override;

        $bookinglimits = json_decode($this->data->limits);


        foreach ($bookinglimits as $limit) {
            if ($limit->enableout || $this->overridevalid == 1) {
                $bookable['from'][$limit->station] = true;
            } else {
                $bookable['from'][$limit->station] = false;
            }
            if ($limit->enableret || $this->overridevalid == 1) {
                $bookable['to'][$limit->station] = true;
            } else {
                $bookable['to'][$limit->station] = false;
            }
        }
        */
    }
}
