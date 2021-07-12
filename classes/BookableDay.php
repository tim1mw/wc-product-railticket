<?php

namespace wc_railticket;
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class BookableDay {
    private $data;
    public $timetable, $fares;

    private function __construct($data, $timetable = false, $fares = false) {
        $this->railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
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

    public static function is_month_bookable($month, $year) {
        global $wpdb;

        $firstday = $year."-".str_pad($month, 2, '0', STR_PAD_LEFT)."-01";
        $lastday = date("Y-m-t", strtotime($firstday));
        $today = new \DateTime();
        $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
        $today->setTimeZone($railticket_timezone);

        $data = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}wc_railticket_bookable ".
            "WHERE date >= '".$firstday."' AND date <= '".$lastday."' AND date >= '".$today->format('Y-m-d')."' AND bookable = 1 ");

        if ($data > 0) {

            return true;
        } else {
            return false;
        }
    }

    public static function is_date_bookable(\DateTime $date, $skiptime = false) {
        global $wpdb;

        $today = new \DateTime();
        $railticket_timezone = new \DateTimeZone(get_option('timezone_string'));
        $today->setTimeZone($railticket_timezone);
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
                return false;
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
        $data->minprice = get_option('wc_product_railticket_min_price');
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

    public static function create_timetable_date($date, $timetableid) {
        global $wpdb;
        // Check the date doesn't exist already
        $datecheck = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wc_railticket_dates WHERE date='".$date."'");
        if ($datecheck) {
            return false;
        }

        $data = array('date' => $date, 'timetableid' => $timetableid);
        $wpdb->insert("{$wpdb->prefix}wc_railticket_dates", $data);
        return true;
    }

    public static function delete_timetable_date($date, $checkempty = true) {
        global $wpdb;

        if ($checkempty) {
            $bk = self::get_bookable_day($date);
            if ($bk && $bk->count_bookings() > 0) {
                return false;
            }
        }

        $data = array('date' => $date);
        $wpdb->delete("{$wpdb->prefix}wc_railticket_dates", $data);
        $wpdb->delete("{$wpdb->prefix}wc_railticket_bookable", $data);
        return true;
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

                // These parameters are a cache based on the composition so should only be updated via the composition to ensure consistency
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

            if (array_key_exists('timetableid', $filtered)) {
                $wpdb->update("{$wpdb->prefix}wc_railticket_dates", array('timetableid' => $filtered['timetableid']),
                    array('date' => $this->data->date));
            }
        }
    }

    public function set_bookable($bookable) {
        global $wpdb;
        $this->data->bookable = $bookable;
        $wpdb->update("{$wpdb->prefix}wc_railticket_bookable", array('bookable' => $bookable), array('id' => $this->data->id));
    }

    public function sold_out() {
        return (bool) $this->data->soldout;
    }

    public function get_min_price() {
        return $this->data->minprice;
    }

    public function special_only() {
        return (bool) $this->data->specialonly;
    }

    public function is_bookable() {
        return (bool) $this->data->bookable;
    }

    public function same_service_return() {
        return (bool) $this->data->sameservicereturn;
    }

    public function get_date($format = false) {
        if (!$format) {
            return $this->data->date;
        }

        $jdate = \DateTime::createFromFormat('Y-m-d', $this->data->date, $this->railticket_timezone);
        return railticket_timefunc(get_option('wc_railticket_date_format'), $jdate->getTimeStamp());
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

    public function get_all_bookings($order = false, $objects = false) {
        global $wpdb;
        if ($order) {
            $order = "ORDER BY ".$order;
        } else {
            $order = '';
        }

        $bks = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_railticket_bookings WHERE date = '".$this->data->date."' ".$order);
        if (!$objects) {
            return $bks;
        }

        $bookings = array();
        foreach ($bks as $bk) {
            $bookings[] = new Booking($bk, $this);
        }

        return $bookings;
    }

    public function get_all_order_ids() {
        global $wpdb;

        // Woocommerce orders are difficult to get directly, so pull from the bookings table and exclude duplicates
        $allids = array();
        $woobks = $wpdb->get_results("SELECT DISTINCT wooorderid FROM {$wpdb->prefix}wc_railticket_bookings WHERE date = '".$this->data->date."' ".
            "AND woocartitem = '' AND manual = 0");
        foreach($woobks as $bk) {
            $allids[] = $bk->wooorderid;
        }

        // Manual bookings only record the date against the booking not the order, so pull from bookings an exclude duplicates
        $mbks = $wpdb->get_results("SELECT DISTINCT manual FROM {$wpdb->prefix}wc_railticket_bookings WHERE date = '".$this->data->date."' ".
            "AND woocartitem = '' AND wooorderid = 0");

        foreach ($mbks as $mb) {
            $allids[] = 'M'.$mb->manual;
        }

        return $allids;
    }

    public function count_bookings() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}wc_railticket_bookings WHERE date = '".$this->data->date."'");
    }

    /*
    * Gets all the bookings for a specific departure from a specific station, does not return through bookings.
    * @param $station The departure station
    * @param $deptime The departure time
    * @param $direction The direction of the service
    **/

    public function get_bookings_from_station(Station $station, $deptime, $direction, $order = false) {
        global $wpdb;

        if ($order) {
            $order = "ORDER BY ".$order;
        } else {
            $order = '';
        }

        $bks = $wpdb->get_results("SELECT bookings.* ".
            "FROM {$wpdb->prefix}wc_railticket_bookings bookings ".
            "WHERE bookings.date='".$this->data->date."' AND ".
            "bookings.time = '".$deptime."' AND bookings.fromstation = ".$station->get_stnid()." AND bookings.direction = '".$direction."' ".$order);

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

    public function get_allocation_type($format = false) {
        if ($format) {
            if ($this->data->allocateby == 'seat') {
                return __('Seat', 'wc_railticket');
            }
            return __('Bay', 'wc_railticket');
        }
        return $this->data->allocateby;
    }

    public function get_specials($dataonly = false) {
        return Special::get_specials($this->data->date, $dataonly);
    }

    public function get_specials_onsale_data($nodisable) {
        $sps = Special::get_specials($this->data->date);
        $data = array();
        if (!$sps) {
            return $data;
        }
        foreach ($sps as $sp) {
            $t = $sp->get_onsale_data();
            $t->classes = '';
            $trainservice = new \wc_railticket\TrainService($this, $sp->get_from_station(), $sp->get_dep_id(), $sp->get_to_station());

            $capused = $trainservice->get_inventory(false, false);
            $t->seatsleft = $capused->totalseats;
            if ($capused->totalseats == 0) {
                $t->seatsleftstr = __('FULL - please try another train', 'wc_railticket');
                $t->classes .= " railticket_full";
                if ($nodisable) {
                    $t->classes .= ' railticket_late';
                } else {
                    $t->disabled = 'disabled';
                    $t->notbookable = true;
                }
            } else {
                $t->seatsleftstr = $capused->totalseats.' '.__('empty seats', 'wc_railticket');
            }

            $data[] = $t;
        }
        return $data;
    }

    public function get_bookable_trains(Station $from, Station $to, $nodisable, $after = false, $disableafter = false) {
        $direction = $from->get_direction($to);
        $times = $this->timetable->get_times($from, $direction, "deps", true, $to);

        if ($after) {
            $after = ($after->hour*60) + $after->min;
        }
        if ($disableafter) {
            $disableafter = ($disableafter->hour*60) + $disableafter->min;
        }
        $nowdt = new \DateTime();
        $nowdt->setTimezone($this->railticket_timezone);

        if ($nowdt->format('Y-m-d') == $this->data->date) {
            $today = true;
        } else {
            $today = false;
        }

        $now = (intval($nowdt->format("H"))*60) + intval($nowdt->format("i"));
        $grace = intval(get_option('wc_product_railticket_bookinggrace'));

        $ftimes = array();

        // The timetable won't tell us if the train can actually be booked. AKA, do we have seats for this trip
        foreach ($times as $time) {
            // Filter out trains that don't stop at the station we want to go to (the timetable just tells us it doesn't stop)
            if ($time->stopsat === false) {
                continue;
            }

            $dt = ($time->hour*60) + $time->min;
            $time->classes = '';
            $time->disabled = '';
            $time->notbookable = false;

            // Filter out trains which are after the specified time
            if ($after) {
                if ($dt < $after) {
                    continue;
                }
            }

            if ($today) {
                $ext = intval($dt + $grace);

                if ($now > $ext) {
                    if ($nodisable) {
                        $time->classes .= ' railticket_late';
                    } else {
                        $time->disabled = 'disabled';
                        $time->notbookable = true;
                    }
                }

                if ($now >= $dt && $now <= $ext) {
                    $time->classes .= ' railticket_late';
                }
            }

            if ($disableafter) {
                if ($dt < $disableafter) {
                    if ($nodisable) {
                        $time->classes .= ' railticket_late';
                    } else {
                        $time->disabled = 'disabled';
                        $time->notbookable = true;
                    }
                }
            }

            if (!$time->notbookable) {
                $trainservice = new \wc_railticket\TrainService($this, $from, $time->key, $to);
                $capused = $trainservice->get_inventory(false, false);
                $time->seatsleft = $capused->totalseats;
                if ($capused->totalseats == 0) {
                    $time->seatsleftstr = __('FULL - please try another train', 'wc_railticket');
                    $time->classes .= " railticket_full";
                    if ($nodisable) {
                        $time->classes .= ' railticket_late';
                    } else {
                        $time->disabled = 'disabled';
                        $time->notbookable = true;
                    }
                } else {
                    $time->seatsleftstr = $capused->totalseats.' '.__('empty seats', 'wc_railticket');
                }
            }

            $time->arr = __('Arrives', 'wc_railticket').' '.$time->stopsat->formatted;

            $ftimes[] = $time;
        }

        return $ftimes;
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
