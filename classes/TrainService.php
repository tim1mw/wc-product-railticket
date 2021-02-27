<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class TrainService {
    private $bookableday, $deptime, $fromstation, $direction;
    public $special;

    public function __construct(BookableDay $bookableday, Station $fromstation, $deptime, Station $tostation) {
        $this->bookableday = $bookableday;
        $this->fromstation = $fromstation;
        $this->tostation = $fromstation;
        $this->deptime = $deptime;
        $this->direction = $fromstation->get_direction($tostation);

        // Is this is a special
        if (strpos($deptime, "s:") === 0) {
            $this->special = Special::get_special($deptime);
        } else {
            $this->special = false;
        }
    }

    public function get_inventory($baseonly = false, $noreserve = false, $onlycollected = false) {
        global $wpdb;

        if ($this->bookableday->sold_out() || !$this->bookableday->is_bookable()) {
            return array();
        }

        // TODO: Need to get origin station dep time here when intermediate stops are enabled!
        switch ($this->bookableday->get_daytype()) {
            case 'simple':
                $basebays = (array) $this->bookableday->get_bays();
                break;
            case 'pertrain':
                $direction = $this->direction;
                $formations = $this->bookableday->get_bays();
                $deptime = $this->deptime;
                $set = $formations->$direction->$deptime;
                $basebays = (array) $formations->coachsets->$set;
                break;
        }

        if ($baseonly) {
            return $basebays;
        }

        // Get the bookings we need to subtract from this formation. TODO This doesn't account for bookings from preceeding stations (it needs to).
        $sql = "SELECT {$wpdb->prefix}wc_railticket_booking_bays.* FROM ".
            "{$wpdb->prefix}wc_railticket_bookings ".
            " LEFT JOIN {$wpdb->prefix}wc_railticket_booking_bays ON ".
            " {$wpdb->prefix}wc_railticket_bookings.id = {$wpdb->prefix}wc_railticket_booking_bays.bookingid ".
            " WHERE ".
            "{$wpdb->prefix}wc_railticket_bookings.fromstation = '".$this->fromstation->get_stnid()."' AND ".
            "{$wpdb->prefix}wc_railticket_bookings.date = '".$this->bookableday->get_date()."' AND ".
            "{$wpdb->prefix}wc_railticket_bookings.time = '".$this->deptime."' ";

        if ($onlycollected) {
            $sql .= " AND {$wpdb->prefix}wc_railticket_bookings.collected = '1' ";
        }

        $bookings = $wpdb->get_results($sql);

        foreach ($bookings as $booking) {
            if ($booking->priority) {
                $i = $booking->baysize.'_priority';
            } else {
                $i = $booking->baysize.'_normal';
            }
            if (array_key_exists($i, $basebays)) {
                $basebays[$i] = $basebays[$i] - $booking->num;
            }
        }

        // Take out the booking reserve
        if ($noreserve == false && !$this->bookableday->sell_reserve() && $this->bookableday->has_reserve()) {

            // TODO: Need to get origin station dep time here when intermediate stops are enabled!
            switch ($this->bookableday->get_daytype()) {
                case 'simple':
                    $reserve = (array) $this->bookableday->get_reserve();
                    break;
                case 'pertrain':
                    $ropts = $this->bookableday->get_reserve();
                    $reserve = $ropts->$set;
                    break;
            }

            foreach ($reserve as $i => $num) {
                if (array_key_exists($i, $basebays)) {
                    $basebays[$i] = $basebays[$i] - $num;
                }
            }
        }

        $totalseats = 0;
        foreach ($basebays as $bay => $numleft) {
            $bayd = $this->getBayDetails($bay);
            $totalseats += $bayd[0]*$numleft;
        }

        $bays = new \stdclass();
        $bays->bays = $basebays;
        $bays->totalseats = $totalseats;
        return $bays;
    }

    private function getBayDetails($bay) {
        $parts = explode('_', $bay);
        $parts[0] = intval($parts[0]);
        $parts[2] = $bay;
        return $parts;
    }

    public function get_reserve($format = false) {
        $res = $this->bookableday->get_reserve();

        switch ($this->bookableday->get_daytype()) {
            case 'simple':
                $resset = $res;
                break;
            case 'pertrain':
                $comp = $this->bookableday->get_composition();
                $direction = $this->direction;
                $deptime = $this->deptime;
                $setkey = $comp->$direction->$deptime;
                $resset = $res->$setkey;
                break;
        }

        if ($format) {
            return $this->get_string($resset);
        }

        return $resset;
    }

    public function get_coachset($format = false) {
        $comp = $this->bookableday->get_composition();

        switch ($this->bookableday->get_daytype()) {
            case 'simple':
                $cset = $comp->coachset;
                break;
            case 'pertrain':
                $direction = $this->direction;
                $deptime = $this->deptime;
                $setkey = $comp->$direction->$deptime;
                $cset = $comp->coachsets->$setkey->coachset;
                break;
        }

        if ($format) {
            return $this->get_string($cset);
        }

        return $cset;
    }

    private function get_string($r) {
        $r = (array) $r;
        $str = '';
        foreach ($r as $i => $num) {
            if ($num > 0) {
                $str .= $i." x".$num.", ";
            }
        }

        return substr($str, 0, strlen($str)-2);
    }

}
