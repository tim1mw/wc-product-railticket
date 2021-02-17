<?php

namespace wc_railticket;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class TrainService {
    private $bookableday, $deptime, $fromstation;

    public function __construct(BookableDay $bookableday, Station $fromstation, $deptime, $direction, $special) {
        $this->bookableday = $bookableday;
        $this->fromstation = $fromstation;
        if ($special && strpos($deptime, ':') === false) {
            $deptime = "s:".$deptime;
        }
        $this->deptime = $deptime;
        $this->direction = $direction;
    }

    public function get_inventory($baseonly = false, $noreserve = false, $onlycollected = false) {
        global $wpdb;

        if ($this->bookableday->sold_out() || !$this->bookableday->is_bookable()) {
            return array();
        }

        // NOTE: Need to get origin station dep time here when intermediate stops are enabled!
        switch ($this->bookableday->get_daytype()) {
            case 'simple':
                $basebays = (array) $this->bookableday->get_bays();
                break;
            case 'pertrain':
                $direction = $this->direction;
                $formations = $this->bookableday->get_bays();
                $set = $formations->$direction->$deptime;
                $basebays = (array) $formations->coachsets->$set;
                break;
        }

        if ($baseonly) {
            return $basebays;
        }

        // Get the bookings we need to subtract from this formation. This doesn't account for bookings from preceeding stations (it needs to).
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
        if ($noreserve == false && $this->bookableday->sell_reserve() && $this->bookableday->has_reserve()) {

            // NOTE: Need to get origin station dep time here when intermediate stops are enabled!
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
}
